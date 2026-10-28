// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * "Send grades" page (v2).
 *
 * - Tabs: Send grades / Send history.
 * - Activity selector + minimum approval grade (pre-filled from the
 *   block_mwa_dashboard_get_grades_send_options web service) drive the green /
 *   red / yellow classification shown per student. The classification only
 *   affects the UI (result column + quick filters); the email content itself
 *   is always built on the server (global grade + full activity breakdown).
 * - Quick filters (passed / failed / pending + all) and name search.
 * - Preview and batch send (email + Moodle message) with per-student results.
 * - History of sends (lazy loaded, paged, with a status filter).
 * - Centered confirmation / alert dialogs (no window.confirm / window.alert).
 *
 * All the text is server-rendered: the preview and send web services build
 * the exact same message on the server
 * (block_mwa_dashboard\external::build_grades_mail), so another student's
 * grades can never leak into one email.
 *
 * @module     block_mwa_dashboard/enviarnotas
 * @copyright  2026 Bruno Porto
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax'], function(Ajax) {

    'use strict';

    // Call a web service through core/ajax (this core build has no core/webservices module).
    // Ajax.call([...])[0] is a promise that resolves to the external function's return value.
    var callWS = function(methodname, args) {
        var calls = Ajax.call([{
            methodname: methodname,
            args: args || {}
        }]);
        return calls[0];
    };

    var HIST_PAGE = 25;

    var courseid = 0;
    var groupid = 0;
    var str = {};
    var rawRows = [];          // parsed student rows from get_grades (raw data kept for acts).
    var students = [];         // {userid, name, total, totalmax, acts:{seq:value}}
    var activities = [];       // [{seq, cmid, name, modname, grademax, passmark, hascustompassmark}]
    var selSeq = null;         // activity being evaluated (null = course totals only).
    var filter = 'all';        // all | passed | failed | pending
    var searchText = '';       // lower-cased, visibility only (does not touch checkboxes).
    var studentsReady = false;
    var metaReady = false;
    var loadError = false;
    var uiBuilt = false;
    var busy = false;
    var els = {};
    var rowEls = {};           // uid -> {tr, box, badge}
    var hist = {started: false, busy: false, state: '', offset: 0, total: 0, rows: []};
    var modalOnOk = null;
    // Preview editor (plan T1) + selected-student chips (plan T3a).
    var selOrder = [];           // checked students, painted in table order (stable chips).
    var previewActive = null;    // student currently shown in the preview.
    var previewCache = {};       // userid -> get_grades_mail_preview response (no re-query).
    var commentEditing = false;  // T1/T8: is the preview comment zone editable right now.
    // T8: the standalone "optional message" textarea was REMOVED (user decision 28-oct).
    // The message (title + body) lives ONLY inside the preview editable zone, below
    // the grades table. These are the module-level values synced from that zone;
    // they are sent to the preview and send web services as `comment` / `commentlabel`.
    var commentValue = '';
    var commentLabelValue = '';  // '' = use the server default label.

    var t = function(key) {
        return (typeof str[key] === 'string' && str[key] !== '') ? str[key] : key;
    };

    var esc = function(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };

    var fmt = function(value) {
        var num = Number(value);
        if (value === null || value === undefined || value === '' || !isFinite(num)) {
            return '';
        }
        return (Math.round(num * 100) / 100).toString();
    };

    var isnum = function(value) {
        if (value === null || value === undefined || value === '') {
            return false;
        }
        return isFinite(Number(value));
    };

    // ---------- Classification (UI only: result column + quick filters). ----------

    var currentActivity = function() {
        for (var i = 0; i < activities.length; i++) {
            if (parseInt(activities[i].seq, 10) === selSeq) {
                return activities[i];
            }
        }
        return null;
    };

    var passmarkValue = function() {
        if (!els.passmark) {
            return 0;
        }
        var v = els.passmark.value;
        return isnum(v) ? Number(v) : 0;
    };

    var canClassify = function() {
        return selSeq !== null && passmarkValue() > 0;
    };

    var classify = function(s) {
        if (!canClassify()) {
            return null;
        }
        var grade = s.acts[selSeq];
        if (!isnum(grade)) {
            return 'pending';
        }
        return (Number(grade) >= passmarkValue()) ? 'passed' : 'failed';
    };

    var rowVisible = function(s) {
        if (filter !== 'all' && classify(s) !== filter) {
            return false;
        }
        if (searchText !== '') {
            if (s.name.toLowerCase().indexOf(searchText) === -1) {
                return false;
            }
        }
        return true;
    };

    // ---------- Modal (centered confirm/alert, replaces window.confirm/alert). ----------

    var buildModal = function() {
        var el = document.createElement('div');
        el.className = 'mwa-gm-modal';
        el.id = 'mwaGmModal';
        el.hidden = true;
        el.innerHTML =
            '<div class="mwa-gm-modal-backdrop"></div>' +
            '<div class="mwa-gm-modal-box" role="dialog" aria-modal="true">' +
            '  <h2 id="mwaGmModalTitle"></h2>' +
            '  <p id="mwaGmModalBody"></p>' +
            '  <div class="mwa-gm-actions mwa-gm-modal-actions">' +
            '    <button type="button" class="mwa-gm-btn mwa-gm-btn-sec" id="mwaGmModalCancel"></button>' +
            '    <button type="button" class="mwa-gm-btn mwa-gm-btn-primary" id="mwaGmModalOk"></button>' +
            '  </div>' +
            '</div>';
        els.wrap.appendChild(el);
        els.modal = el;
        els.modalTitle = el.querySelector('#mwaGmModalTitle');
        els.modalBody = el.querySelector('#mwaGmModalBody');
        els.modalCancel = el.querySelector('#mwaGmModalCancel');
        els.modalOk = el.querySelector('#mwaGmModalOk');
        els.modalBackdrop = el.querySelector('.mwa-gm-modal-backdrop');

        els.modalBackdrop.addEventListener('click', function() { closeModal(); });
        els.modalCancel.addEventListener('click', function() { closeModal(); });
        els.modalOk.addEventListener('click', function() {
            var cb = modalOnOk;
            modalOnOk = null;
            closeModal();
            if (typeof cb === 'function') {
                cb();
            }
        });
        document.addEventListener('keydown', function(ev) {
            if (!els.modal.hidden && ev.key === 'Escape') {
                closeModal();
            }
        });
    };

    var setModal = function(title, body, confirmMode) {
        els.modalTitle.textContent = title;
        els.modalBody.textContent = body;
        els.modalOk.style.display = confirmMode ? '' : 'none';
        els.modalCancel.textContent = t('confirm_cancel');
        els.modalOk.textContent = t('confirm_send');
        els.modal.hidden = false;
        window.setTimeout(function() {
            (confirmMode ? els.modalOk : els.modalCancel).focus();
        }, 0);
    };

    var openConfirm = function(title, body, onOk) {
        modalOnOk = onOk;
        setModal(title, body, true);
    };

    var openAlert = function(title, body) {
        modalOnOk = null;
        setModal(title, body, false);
    };

    var closeModal = function() {
        modalOnOk = null;
        if (els.modal) {
            els.modal.hidden = true;
        }
    };

    // ---------- UI construction. ----------

    var chipHtml = function(kind, label) {
        return '<button type="button" class="mwa-gm-chip mwa-gm-chip-' + kind + '" data-filter="' + kind + '">' +
            esc(label) + '</button>';
    };

    var buildUI = function() {
        els.wrap.innerHTML =
            '<div class="mwa-gm-wrap">' +
            // Tabs.
            '  <div class="mwa-gm-tabs">' +
            '    <button type="button" class="mwa-gm-tab is-active" id="mwaGmTabSend">' + esc(t('tab_send')) + '</button>' +
            '    <button type="button" class="mwa-gm-tab" id="mwaGmTabHistory">' + esc(t('tab_history')) + '</button>' +
            '  </div>' +
            // Panel: send.
            '  <div id="mwaGmPanelSend">' +
            '    <section class="mwa-gm-card">' +
            '      <div class="mwa-gm-field">' +
            '        <label class="mwa-gm-label" for="mwaGmActivity">' + esc(t('evalselect')) + '</label>' +
            '        <select id="mwaGmActivity" class="mwa-gm-select"></select>' +
            '      </div>' +
            '      <div class="mwa-gm-field">' +
            '        <label class="mwa-gm-label" for="mwaGmPassmark">' + esc(t('passmin')) + '</label>' +
            '        <input type="number" min="0" step="0.01" id="mwaGmPassmark" class="mwa-gm-input mwa-gm-input-narrow" disabled>' +
            '        <p class="mwa-gm-hint" id="mwaGmPassHint"></p>' +
            '      </div>' +
            '    </section>' +
            '    <section class="mwa-gm-card">' +
            '      <div class="mwa-gm-cardhead">' +
            '        <h2>' + esc(t('students')) + '</h2>' +
            '        <span class="mwa-gm-count" id="mwaGmCount"></span>' +
            '      </div>' +
            '      <div class="mwa-gm-actions">' +
            '        <input type="search" id="mwaGmSearch" class="mwa-gm-input mwa-gm-input-search" ' +
            '           aria-label="' + esc(t('searchplaceholder')) + '" placeholder="' + esc(t('searchplaceholder')) + '">' +
            '        <button type="button" class="mwa-gm-btn mwa-gm-btn-sec" id="mwaGmSelectAll">' + esc(t('selectall')) + '</button>' +
            '        <button type="button" class="mwa-gm-btn mwa-gm-btn-sec" id="mwaGmDeselectAll">' + esc(t('deselectall')) + '</button>' +
            '      </div>' +
            '      <div class="mwa-gm-chips" id="mwaGmChips">' +
            '        ' + chipHtml('all', t('filter_all')) +
            '        ' + chipHtml('passed', t('filter_passed')) +
            '        ' + chipHtml('failed', t('filter_failed')) +
            '        ' + chipHtml('pending', t('filter_pending')) +
            '      </div>' +
            '      <table class="mwa-gm-table" id="mwaGmTable">' +
            '        <thead><tr>' +
            '          <th class="mwa-gm-check"></th>' +
            '          <th>' + esc(t('col_student')) + '</th>' +
            '          <th class="num">' + esc(t('col_grade')) + '</th>' +
            '          <th>' + esc(t('resultcol')) + '</th>' +
            '        </tr></thead>' +
            '        <tbody id="mwaGmRows"></tbody>' +
            '      </table>' +
            '      <p class="mwa-gm-empty" id="mwaGmEmpty" style="display:none">' + esc(t('nostudents')) + '</p>' +
            '    </section>' +
            '    <p class="mwa-gm-hint">' + esc(t('comment_hint')) + '</p>' +
            '    <div class="mwa-gm-actions mwa-gm-actions-end">' +
            '      <button type="button" class="mwa-gm-btn mwa-gm-btn-sec" id="mwaGmPreviewBtn">' + esc(t('preview')) + '</button>' +
            '      <button type="button" class="mwa-gm-btn mwa-gm-btn-primary" id="mwaGmSendBtn">' + esc(t('send')) + '</button>' +
            '    </div>' +
            '    <section class="mwa-gm-card" id="mwaGmPreviewCard" style="display:none">' +
            '      <div class="mwa-gm-cardhead">' +
            '        <h2>' + esc(t('previewtitle')) + '</h2>' +
            '        <span class="mwa-gm-student" id="mwaGmPreviewStudent"></span>' +
            '        <button type="button" class="mwa-gm-btn mwa-gm-btn-sec" id="mwaGmPreviewEdit">' + esc(t('preview_edit')) + '</button>' +
            '      </div>' +
            '      <p class="mwa-gm-hint">' + esc(t('preview_chips_hint')) + '</p>' +
            '      <div class="mwa-gm-chips mwa-gm-chips-students" id="mwaGmPreviewChips" style="display:none"></div>' +
            '      <p class="mwa-gm-subjline"><span class="mwa-gm-subjlabel">' + esc(t('previewsubject')) + ':</span> ' +
            '        <span id="mwaGmPreviewSubject"></span></p>' +
            '      <div class="mwa-gm-mail" id="mwaGmPreviewBody"></div>' +
            '    </section>' +
            '    <section class="mwa-gm-card" id="mwaGmResultsCard" style="display:none">' +
            '      <h2>' + esc(t('results')) + '</h2>' +
            '      <p class="mwa-gm-summary" id="mwaGmResultsSummary"></p>' +
            '      <table class="mwa-gm-table">' +
            '        <thead><tr>' +
            '          <th>' + esc(t('col_student')) + '</th>' +
            '          <th>' + esc(t('results')) + '</th>' +
            '          <th class="num">' + esc(t('channel_email')) + '</th>' +
            '          <th class="num">' + esc(t('channel_moodle')) + '</th>' +
            '        </tr></thead>' +
            '        <tbody id="mwaGmResultsRows"></tbody>' +
            '      </table>' +
            '    </section>' +
            '  </div>' +
            // Panel: history.
            '  <div id="mwaGmPanelHistory" style="display:none">' +
            '    <section class="mwa-gm-card">' +
            '      <div class="mwa-gm-cardhead">' +
            '        <h2>' + esc(t('hist_title')) + '</h2>' +
            '        <select id="mwaGmHistState" class="mwa-gm-select mwa-gm-select-narrow">' +
            '          <option value="">' + esc(t('hist_state_all')) + '</option>' +
            '          <option value="sent">' + esc(t('hist_state_sent')) + '</option>' +
            '          <option value="error">' + esc(t('hist_state_error')) + '</option>' +
            '        </select>' +
            '        <button type="button" class="mwa-gm-btn mwa-gm-btn-sec" id="mwaGmHistClear" disabled>' + esc(t('hist_clear')) + '</button>' +
            '      </div>' +
            '      <table class="mwa-gm-table mwa-gm-table-hist" id="mwaGmHistTable" style="display:none">' +
            '        <thead><tr>' +
            '          <th>' + esc(t('col_date')) + '</th>' +
            '          <th>' + esc(t('col_time')) + '</th>' +
            '          <th>' + esc(t('col_teacher')) + '</th>' +
            '          <th>' + esc(t('col_student')) + '</th>' +
            '          <th>' + esc(t('col_status')) + '</th>' +
            '          <th>' + esc(t('col_subject')) + '</th>' +
            '          <th class="mwa-gm-delcol"></th>' +
            '        </tr></thead>' +
            '        <tbody id="mwaGmHistRows"></tbody>' +
            '      </table>' +
            '      <p class="mwa-gm-empty" id="mwaGmHistEmpty" style="display:none">' + esc(t('hist_empty')) + '</p>' +
            '      <div class="mwa-gm-actions mwa-gm-actions-end">' +
            '        <button type="button" class="mwa-gm-btn mwa-gm-btn-sec" id="mwaGmHistMore" style="display:none">' + esc(t('hist_more')) + '</button>' +
            '      </div>' +
            '    </section>' +
            '  </div>' +
            '</div>';
    };

    var bindEls = function() {
        els.tabSend = els.wrap.querySelector('#mwaGmTabSend');
        els.tabHistory = els.wrap.querySelector('#mwaGmTabHistory');
        els.panelSend = els.wrap.querySelector('#mwaGmPanelSend');
        els.panelHistory = els.wrap.querySelector('#mwaGmPanelHistory');
        els.activity = els.wrap.querySelector('#mwaGmActivity');
        els.passmark = els.wrap.querySelector('#mwaGmPassmark');
        els.passHint = els.wrap.querySelector('#mwaGmPassHint');
        els.count = els.wrap.querySelector('#mwaGmCount');
        els.search = els.wrap.querySelector('#mwaGmSearch');
        els.selectAll = els.wrap.querySelector('#mwaGmSelectAll');
        els.deselectAll = els.wrap.querySelector('#mwaGmDeselectAll');
        els.chips = els.wrap.querySelector('#mwaGmChips');
        els.table = els.wrap.querySelector('#mwaGmTable');
        els.rows = els.wrap.querySelector('#mwaGmRows');
        els.empty = els.wrap.querySelector('#mwaGmEmpty');
        els.previewBtn = els.wrap.querySelector('#mwaGmPreviewBtn');
        els.sendBtn = els.wrap.querySelector('#mwaGmSendBtn');
        els.previewCard = els.wrap.querySelector('#mwaGmPreviewCard');
        els.previewStudent = els.wrap.querySelector('#mwaGmPreviewStudent');
        els.previewSubject = els.wrap.querySelector('#mwaGmPreviewSubject');
        els.previewBody = els.wrap.querySelector('#mwaGmPreviewBody');
        els.previewEdit = els.wrap.querySelector('#mwaGmPreviewEdit');
        els.previewChips = els.wrap.querySelector('#mwaGmPreviewChips');
        els.resultsCard = els.wrap.querySelector('#mwaGmResultsCard');
        els.resultsSummary = els.wrap.querySelector('#mwaGmResultsSummary');
        els.resultsRows = els.wrap.querySelector('#mwaGmResultsRows');
        els.histState = els.wrap.querySelector('#mwaGmHistState');
        els.histTable = els.wrap.querySelector('#mwaGmHistTable');
        els.histRows = els.wrap.querySelector('#mwaGmHistRows');
        els.histEmpty = els.wrap.querySelector('#mwaGmHistEmpty');
        els.histMore = els.wrap.querySelector('#mwaGmHistMore');
        els.histClear = els.wrap.querySelector('#mwaGmHistClear');
    };

    var buildRows = function() {
        var html = '';
        students.forEach(function(s) {
            html +=
                '<tr>' +
                '<td class="mwa-gm-check"><input type="checkbox" class="mwa-gm-checkbox" data-uid="' + s.userid + '"></td>' +
                '<td>' + esc(s.name) + '</td>' +
                '<td class="num">' + gradeCell(s) + '</td>' +
                '<td><span class="mwa-gm-resultcell"></span></td>' +
                '</tr>';
        });
        els.rows.innerHTML = html;
        els.empty.style.display = (students.length === 0) ? '' : 'none';
        els.table.style.display = (students.length === 0) ? 'none' : '';
        // Fresh dataset: reset the selection trace, preview cache, the T1
        // editor and any previously painted preview (stale content must never
        // survive a reload of the student rows).
        selOrder = [];
        previewActive = null;
        previewCache = {};
        commentEditing = false;
        if (els.previewCard) {
            els.previewCard.style.display = 'none';
        }
        rowEls = {};
        Array.prototype.slice.call(els.rows.querySelectorAll('tr')).forEach(function(tr) {
            var box = tr.querySelector('.mwa-gm-checkbox');
            if (!box) {
                return;
            }
            rowEls[parseInt(box.getAttribute('data-uid'), 10)] = {
                tr: tr,
                box: box,
                badge: tr.querySelector('.mwa-gm-resultcell')
            };
        });
        refreshResultColumn();
        applyView(false);
    };

    // ---------- Students: selection, filters, search, result column. ----------

    var checkboxes = function() {
        return Array.prototype.slice.call(els.rows.querySelectorAll('.mwa-gm-checkbox'));
    };

    var updateCount = function() {
        var n = 0;
        checkboxes().forEach(function(box) {
            if (box.checked) {
                n++;
            }
        });
        els.count.textContent = t('selectedcount').replace('{count}', String(n));
    };

    var selectedIds = function() {
        var ids = [];
        checkboxes().forEach(function(box) {
            if (box.checked) {
                ids.push(parseInt(box.getAttribute('data-uid'), 10));
            }
        });
        return ids;
    };

    var gradeCell = function(s) {
        if (!isnum(s.total)) {
            return '<span class="mwa-gm-nograde">' + esc(t('nograde')) + '</span>';
        }
        var text = fmt(s.total);
        if (isnum(s.totalmax)) {
            text = text + ' / ' + fmt(s.totalmax);
        }
        return esc(text);
    };

    var nameOf = function(row) {
        var name = ((row['First name'] || '') + ' ' + (row['Last name'] || ''))
            .replace(/\s+/g, ' ').trim();
        return name || ('#' + row['User ID']);
    };

    var resultCellHtml = function(s) {
        var c = classify(s);
        if (c === null) {
            return '<span class="mwa-gm-dash">&ndash;</span>';
        }
        return '<span class="mwa-gm-badge mwa-gm-badge-' + c + '">' + esc(t('result_' + c)) + '</span>';
    };

    var refreshResultColumn = function() {
        students.forEach(function(s) {
            var rec = rowEls[s.userid];
            if (rec && rec.badge) {
                rec.badge.innerHTML = resultCellHtml(s);
            }
        });
    };

    var applyView = function(checkGroup) {
        students.forEach(function(s) {
            var rec = rowEls[s.userid];
            if (!rec) {
                return;
            }
            rec.tr.style.display = rowVisible(s) ? '' : 'none';
            if (checkGroup) {
                rec.box.checked = (classify(s) === filter);
            }
        });
        refreshSelection();
    };

    var setChipState = function(kind) {
        filter = kind;
        Array.prototype.slice.call(els.chips.querySelectorAll('.mwa-gm-chip')).forEach(function(btn) {
            btn.classList.toggle('is-active', btn.getAttribute('data-filter') === kind);
        });
    };

    var refreshChipsDisabled = function() {
        var ok = canClassify();
        Array.prototype.slice.call(els.chips.querySelectorAll('.mwa-gm-chip')).forEach(function(btn) {
            var k = btn.getAttribute('data-filter');
            if (k !== 'all') {
                btn.disabled = !ok;
            }
        });
    };

    var onChipClick = function(kind) {
        if (kind !== 'all' && !canClassify()) {
            return;
        }
        setChipState(kind);
        // The colored chips mark that group (unchecking the rest); "all" only
        // restores the view and keeps the current selection.
        applyView(kind !== 'all');
    };

    var setAllVisible = function(on) {
        students.forEach(function(s) {
            var rec = rowEls[s.userid];
            if (rec && rec.tr.style.display !== 'none') {
                rec.box.checked = on;
            }
        });
        updateCount();
    };

    var syncAfterClassifyChange = function() {
        if (filter !== 'all' && !canClassify()) {
            setChipState('all');
        }
        refreshChipsDisabled();
        refreshResultColumn();
        applyView(false);
    };

    // ---------- Activity / pass mark. ----------

    var refreshPassmarkHint = function() {
        var act = currentActivity();
        var msg = '';
        if (act) {
            if (Number(act.grademax) > 0) {
                msg = (act.hascustompassmark ? t('passmin_custom') : t('passmin_fallback'));
            } else {
                msg = t('passmin_none');
            }
        } else if (students.length > 0 && activities.length === 0) {
            // T5/T6: the course has students but no graded activity at all —
            // since T6, hidden items are selectable, so reaching this state
            // means the course simply has no grade item to offer.
            msg = t('evalselect_empty');
        }
        els.passHint.textContent = msg;
    };

    var populateActivities = function() {
        var opts = '';
        activities.forEach(function(a) {
            var label = (a && a.name) ? a.name : ('#' + a.cmid);
            // T6: tag grade items hidden in the gradebook (still selectable here).
            if (a && a.hidden) {
                label += ' ' + t('act_hidden_tag');
            }
            if (a && Number(a.grademax) > 0) {
                label += ' (' + fmt(a.grademax) + ')';
            }
            opts += '<option value="' + parseInt(a.seq, 10) + '">' + esc(label) + '</option>';
        });
        els.activity.innerHTML = opts;
        els.activity.disabled = (activities.length === 0);
        if (activities.length > 0) {
            selSeq = parseInt(activities[0].seq, 10);
            els.activity.value = String(selSeq);
        } else {
            selSeq = null;
        }
        var act = currentActivity();
        els.passmark.value = (act && Number(act.passmark) > 0) ? act.passmark : '';
        els.passmark.disabled = !act;
        refreshPassmarkHint();
        refreshChipsDisabled();
    };

    // ---------- Preview and send. ----------

    // T3a: the chips trace the checked students (painted in table order, so
    // the sequence is stable no matter how they were checked). The only
    // per-click state kept is which student the preview currently shows.
    var studentNameOf = function(uid) {
        for (var i = 0; i < students.length; i++) {
            if (students[i].userid === uid) {
                return students[i].name;
            }
        }
        return '#' + uid;
    };

    var syncPreviewChips = function() {
        if (!els.previewChips) {
            return;
        }
        var html = '';
        selOrder.forEach(function(uid) {
            var active = (uid === previewActive);
            html +=
                '<button type="button" class="mwa-gm-chip mwa-gm-chip-student' + (active ? ' is-active' : '') + '"' +
                ' data-studentid="' + uid + '">' + esc(studentNameOf(uid)) + '</button>';
        });
        els.previewChips.innerHTML = html;
        els.previewChips.style.display = (selOrder.length > 0) ? '' : 'none';
        if (selOrder.length === 0) {
            // Nothing selected anymore: the preview has nothing to show.
            previewActive = null;
            if (els.previewEdit) {
                commentEditing = false;
                els.previewEdit.textContent = t('preview_edit');
            }
            els.previewCard.style.display = 'none';
        }
    };

    // Rebuilds the checked set + chips after ANY change of the checkboxes
    // (individual check, select/deselect all, colored filter group).
    var refreshSelection = function() {
        updateCount();
        selOrder = [];
        checkboxes().forEach(function(box) {
            if (box.checked) {
                selOrder.push(parseInt(box.getAttribute('data-uid'), 10));
            }
        });
        if (previewActive !== null && selOrder.indexOf(previewActive) === -1) {
            previewActive = (selOrder.length > 0) ? selOrder[0] : null;
        }
        syncPreviewChips();
    };

    // T1: the preview exposes a single editable zone — the teacher comment.
    // The anchor markup mirrors external::build_grades_mail() so the editor
    // works when the server already rendered the section AND when it was
    // painted with an empty comment (injected the first time, then targeted).
    var previewAnchor = function() {
        return els.previewBody ? els.previewBody.querySelector('[data-gm-editable="comment"]') : null;
    };

    var previewCommentField = function() {
        var a = previewAnchor();
        return a ? a.querySelector('[data-gm-field="commenttext"]') : null;
    };

    // T8: the section TITLE is editable too (same anchor, different field).
    var previewLabelField = function() {
        var a = previewAnchor();
        return a ? a.querySelector('[data-gm-field="commentlabel"]') : null;
    };

    var injectCommentAnchor = function() {
        if (!els.previewBody) {
            return null;
        }
        var anchor = document.createElement('div');
        anchor.setAttribute('data-gm-editable', 'comment');
        anchor.setAttribute('style', 'margin:0 0 16px;');
        var label = document.createElement('p');
        label.setAttribute('style', 'margin:0 6px;');
        var strong = document.createElement('strong');
        strong.textContent = (commentLabelValue !== '') ? commentLabelValue : t('email_commentlabel');
        strong.setAttribute('data-gm-field', 'commentlabel');
        label.appendChild(strong);
        var field = document.createElement('p');
        field.setAttribute('data-gm-field', 'commenttext');
        field.setAttribute('style', 'margin:0;');
        anchor.appendChild(label);
        anchor.appendChild(field);
        els.previewBody.insertBefore(anchor, els.previewBody.lastElementChild);
        anchor.querySelectorAll('[data-gm-field]').forEach(function(z) {
            z.addEventListener('input', syncCommentFromEditor);
        });
        return anchor;
    };

    var syncCommentFromEditor = function() {
        var field = previewCommentField();
        if (field) {
            commentValue = (field.innerText || field.textContent || '').replace(/\n+$/g, '').trim();
        }
        var label = previewLabelField();
        if (label) {
            var lab = (label.innerText || label.textContent || '').replace(/\n+$/g, '').trim();
            // '' reverts to the server default label (an empty title means
            // "use the plugin's own text", exactly like the server side).
            commentLabelValue = (lab === t('email_commentlabel')) ? '' : lab;
        }
    };

    var toggleCommentEdit = function() {
        if (commentEditing) {
            commentEditing = false;
            var done = previewCommentField();
            if (done) {
                done.removeAttribute('contenteditable');
                done.classList.remove('mwa-gm-editing');
            }
            var doneLabel = previewLabelField();
            if (doneLabel) {
                doneLabel.removeAttribute('contenteditable');
                doneLabel.classList.remove('mwa-gm-editing');
            }
            if (els.previewEdit) {
                els.previewEdit.textContent = t('preview_edit');
            }
            syncCommentFromEditor();
            return;
        }
        var anchor = previewAnchor() || injectCommentAnchor();
        var field = anchor ? anchor.querySelector('[data-gm-field="commenttext"]') : null;
        if (!field) {
            return;
        }
        var label = anchor ? anchor.querySelector('[data-gm-field="commentlabel"]') : null;
        if (label) {
            label.setAttribute('contenteditable', 'true');
            label.classList.add('mwa-gm-editing');
        }
        field.setAttribute('contenteditable', 'true');
        field.classList.add('mwa-gm-editing');
        if (els.previewEdit) {
            els.previewEdit.textContent = t('preview_done');
        }
        commentEditing = true;
        window.setTimeout(function() {
            field.focus();
        }, 0);
    };

    // Paints one student's preview from a server response and re-syncs the
    // comment zone (title + body) with the CURRENT module values (the cached
    // HTML still holds whatever comment was active when it was fetched).
    var paintPreview = function(userid, res) {
        commentEditing = false;
        if (els.previewEdit) {
            els.previewEdit.textContent = t('preview_edit');
        }
        previewActive = userid;
        els.previewStudent.textContent = (res && res.studentname) || '';
        els.previewSubject.textContent = (res && res.subject) || '';
        // WYSIWYG: the body is always the server HTML; user HTML is never
        // persisted into the DOM longer than one render (plan T1).
        els.previewBody.innerHTML = (res && res.html) || '';
        els.previewCard.style.display = '';
        var field = previewCommentField();
        var label = previewLabelField();
        if (label) {
            label.textContent = (commentLabelValue !== '') ? commentLabelValue : t('email_commentlabel');
            label.addEventListener('input', syncCommentFromEditor);
        }
        if (field) {
            field.innerText = (commentValue || '').trim();
            field.addEventListener('input', syncCommentFromEditor);
        }
        syncPreviewChips();
    };

    var showStudentPreview = function(userid) {
        if (busy) {
            return;
        }
        if (selOrder.indexOf(userid) === -1) {
            return;
        }
        if (previewCache[userid]) {
            paintPreview(userid, previewCache[userid]);
            return;
        }
        busy = true;
        els.previewBtn.disabled = true;
        callWS('block_mwa_dashboard_get_grades_mail_preview', {
            courseid: courseid,
            userid: userid,
            groupid: groupid,
            comment: (commentValue || '').trim(),
            commentlabel: (commentLabelValue || '').trim()
        }).then(function(res) {
            if (res && res.html) {
                previewCache[userid] = res;
            }
            paintPreview(userid, res);
        }).catch(function(err) {
            openAlert(t('error_title'), ((err && err.message) ? err.message : t('error_generic')));
        }).then(function() {
            busy = false;
            els.previewBtn.disabled = false;
        });
    };

    var doPreview = function() {
        if (busy) {
            return;
        }
        var ids = selectedIds();
        if (!ids.length) {
            openAlert(t('error_title'), t('alert_noselection'));
            return;
        }
        // Keep the active student while it is still selected; otherwise the
        // first one in the selection (T3a).
        var uid = (previewActive !== null && selOrder.indexOf(previewActive) !== -1)
            ? previewActive
            : selOrder[0];
        showStudentPreview(uid);
    };

    var channelMark = function(ok) {
        var cls = ok ? 'mwa-gm-channel-yes' : 'mwa-gm-channel-no';
        var mark = ok ? '&#10003;' : '&#10007;';
        return '<span class="' + cls + '">' + mark + '</span>';
    };

    var renderResults = function(res) {
        res = res || {sent: 0, failed: 0, results: []};
        var byId = {};
        students.forEach(function(s) {
            byId[s.userid] = s.name;
        });
        var html = '';
        (res.results || []).forEach(function(r) {
            var name = (r.userid in byId) ? byId[r.userid] : ('#' + r.userid);
            var badgeClass = 'error';
            var badgeLabel = t('status_error');
            if (r.status === 'sent') {
                badgeClass = 'sent';
                badgeLabel = t('status_sent');
            } else if (r.status === 'not_enrolled') {
                badgeClass = 'notenrolled';
                badgeLabel = t('status_notenrolled');
            }
            html +=
                '<tr>' +
                '<td>' + esc(name) + '</td>' +
                '<td><span class="mwa-gm-badge mwa-gm-badge-' + esc(badgeClass) + '">' + esc(badgeLabel) + '</span></td>' +
                '<td class="num">' + channelMark(r.emailsent) + '</td>' +
                '<td class="num">' + channelMark(r.moodlesent) + '</td>' +
                '</tr>';
        });
        els.resultsRows.innerHTML = html;
        els.resultsSummary.innerHTML =
            esc(String(res.sent || 0)) + ' ' + esc(t('status_sent')) + ' &middot; ' +
            esc(String(res.failed || 0)) + ' ' + esc(t('status_error'));
        els.resultsCard.style.display = '';
    };

    var confirmBody = function(count) {
        var act = currentActivity();
        var activityLabel = (act && act.name) ? act.name : t('confirm_noactivity');
        var passnote = canClassify() ? t('confirm_passnote') : '';
        return t('confirm_body')
            .replace('{count}', String(count))
            .replace('{activity}', activityLabel)
            .replace('{passnote}', passnote);
    };

    var doSend = function() {
        if (busy) {
            return;
        }
        var ids = selectedIds();
        if (!ids.length) {
            openAlert(t('error_title'), t('alert_noselection'));
            return;
        }
        openConfirm(t('confirm_title'), confirmBody(ids.length), function() {
            doSendActual(ids);
        });
    };

    var doSendActual = function(ids) {
        busy = true;
        els.sendBtn.disabled = true;
        els.sendBtn.textContent = t('sending');
        callWS('block_mwa_dashboard_send_grades_mail', {
            courseid: courseid,
            userids: ids,
            groupid: groupid,
            comment: (commentValue || '').trim(),
            commentlabel: (commentLabelValue || '').trim()
        }).then(function(res) {
            renderResults(res);
            if (hist.started && res && res.sent > 0) {
                loadHistory(true);
            }
        }).catch(function(err) {
            openAlert(t('error_title'), ((err && err.message) ? err.message : t('error_generic')));
        }).then(function() {
            busy = false;
            els.sendBtn.disabled = false;
            els.sendBtn.textContent = t('send');
        });
    };

    // ---------- History tab. ----------

    var fmtDate = function(ts) {
        var d = new Date(Number(ts) * 1000);
        if (!isFinite(d.getTime())) {
            return '—';
        }
        try {
            return new Intl.DateTimeFormat(undefined, {
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            }).format(d);
        } catch (err) {
            return String(d);
        }
    };

    var fmtTime = function(ts) {
        var d = new Date(Number(ts) * 1000);
        if (!isFinite(d.getTime())) {
            return '—';
        }
        try {
            return new Intl.DateTimeFormat(undefined, {
                hour: '2-digit',
                minute: '2-digit'
            }).format(d);
        } catch (err) {
            return String(d);
        }
    };

    var renderHistory = function() {
        var html = '';
        hist.rows.forEach(function(e) {
            var ok = (e.status === 'sent');
            var badge = ok
                ? '<span class="mwa-gm-badge mwa-gm-badge-sent">' + esc(t('hist_state_sent')) + '</span>'
                : '<span class="mwa-gm-badge mwa-gm-badge-error">' + esc(t('hist_bounced')) + '</span>';
            html +=
                '<tr>' +
                '<td>' + esc(fmtDate(e.timesent)) + '</td>' +
                '<td>' + esc(fmtTime(e.timesent)) + '</td>' +
                '<td>' + esc(e.teachername || '') + '</td>' +
                '<td>' + esc(e.studentname || '') + '</td>' +
                '<td>' + badge + '</td>' +
                '<td class="mwa-gm-subj">' + esc(e.subject || '') + '</td>' +
                '<td>' +
                '  <button type="button" class="mwa-gm-iconbtn" data-delhist="' + esc(e.recordid) + '" title="' + esc(t('hist_del_row')) + '">&#10005;</button>' +
                '</td>' +
                '</tr>';
        });
        els.histRows.innerHTML = html;
        els.histEmpty.style.display = (hist.total === 0) ? '' : 'none';
        els.histTable.style.display = (hist.rows.length > 0) ? '' : 'none';
        els.histMore.style.display = (hist.offset < hist.total) ? '' : 'none';
        if (els.histClear) {
            els.histClear.disabled = (hist.total === 0);
        }
    };

    var loadHistory = function(reset) {
        if (hist.busy) {
            return;
        }
        if (reset) {
            hist.offset = 0;
            hist.rows = [];
            renderHistory();
        }
        hist.busy = true;
        els.histMore.disabled = true;
        callWS('block_mwa_dashboard_get_grades_mail_history', {
            courseid: courseid,
            status: hist.state,
            groupid: groupid,
            limit: HIST_PAGE,
            offset: hist.offset
        }).then(function(res) {
            hist.total = (res && typeof res.total === 'number') ? res.total : 0;
            var list = (res && res.entries) ? res.entries : [];
            hist.rows = hist.rows.concat(list);
            hist.offset = hist.rows.length;
            renderHistory();
        }).catch(function(err) {
            openAlert(t('error_title'), ((err && err.message) ? err.message : t('error_generic')));
        }).then(function() {
            hist.busy = false;
            els.histMore.disabled = false;
        });
    };

    // ---------- Tabs and events. ----------

    var switchTab = function(which) {
        els.tabSend.classList.toggle('is-active', which === 'send');
        els.tabHistory.classList.toggle('is-active', which === 'history');
        els.panelSend.style.display = (which === 'send') ? '' : 'none';
        els.panelHistory.style.display = (which === 'history') ? '' : 'none';
        if (which === 'history' && !hist.started) {
            hist.started = true;
            loadHistory(true);
        }
    };

    var bindEvents = function() {
        els.tabSend.addEventListener('click', function() { switchTab('send'); });
        els.tabHistory.addEventListener('click', function() { switchTab('history'); });

        els.activity.addEventListener('change', function() {
            var v = parseInt(els.activity.value, 10);
            selSeq = (v > 0) ? v : null;
            var act = currentActivity();
            els.passmark.value = (act && Number(act.passmark) > 0) ? act.passmark : '';
            els.passmark.disabled = !act;
            refreshPassmarkHint();
            if (filter !== 'all') {
                setChipState('all');
            }
            syncAfterClassifyChange();
        });

        els.passmark.addEventListener('input', function() {
            refreshPassmarkHint();
            syncAfterClassifyChange();
        });

        els.chips.addEventListener('click', function(ev) {
            var el = ev.target;
            while (el && el !== els.chips) {
                if (String(el.tagName ? el.tagName : '').toUpperCase() === 'BUTTON' && el.getAttribute('data-filter')) {
                    if (!el.disabled) {
                        onChipClick(el.getAttribute('data-filter'));
                    }
                    return;
                }
                el = el.parentNode;
            }
        });

        els.search.addEventListener('input', function() {
            searchText = (els.search.value || '').trim().toLowerCase();
            applyView(false);
        });

        els.selectAll.addEventListener('click', function() { setAllVisible(true); });
        els.deselectAll.addEventListener('click', function() { setAllVisible(false); });

        els.rows.addEventListener('change', function(ev) {
            var target = ev.target;
            if (target && target.className && String(target.className).indexOf('mwa-gm-checkbox') !== -1) {
                refreshSelection();
            }
        });

        els.previewBtn.addEventListener('click', doPreview);
        els.sendBtn.addEventListener('click', doSend);

        // T1/T8: edit the message (title + body) right inside the preview; the
        // module values (commentValue / commentLabelValue) stay in sync while
        // the user types (no more standalone textarea — plan T8).
        els.previewEdit.addEventListener('click', toggleCommentEdit);

        // T3a: chips inside the preview card switch the shown student without
        // re-querying (the first time per student it is cached, see the
        // acceptance checklist: at most one call per student).
        els.previewChips.addEventListener('click', function(ev) {
            var el = ev.target;
            while (el && el !== els.previewChips) {
                if (String(el.tagName || '').toUpperCase() === 'BUTTON' && el.getAttribute('data-studentid')) {
                    showStudentPreview(parseInt(el.getAttribute('data-studentid'), 10));
                    return;
                }
                el = el.parentNode;
            }
        });

        els.histState.addEventListener('change', function() {
            hist.state = els.histState.value;
            loadHistory(true);
        });
        els.histMore.addEventListener('click', function() { loadHistory(false); });

        // T3b: per-row delete (delegated over the paged tbody).
        els.histRows.addEventListener('click', function(ev) {
            var el = ev.target;
            while (el && el !== els.histRows) {
                if (String(el.tagName || '').toUpperCase() === 'BUTTON' && el.getAttribute('data-delhist')) {
                    var rid = parseInt(el.getAttribute('data-delhist'), 10);
                    openConfirm(t('hist_del_title'), t('hist_del_body'), function() {
                        if (busy) {
                            return;
                        }
                        busy = true;
                        callWS('block_mwa_dashboard_delete_grades_mail_record', {id: rid})
                            .then(function(res) {
                                if (res && res.success) {
                                    var ix = -1;
                                    for (var i = 0; i < hist.rows.length; i++) {
                                        if (Number(hist.rows[i].recordid) === rid) {
                                            ix = i;
                                            break;
                                        }
                                    }
                                    if (ix !== -1) {
                                        hist.rows.splice(ix, 1);
                                        if (hist.total > 0) {
                                            hist.total--;
                                        }
                                    }
                                    renderHistory();
                                } else {
                                    // Refused by the backend (not owner, not a
                                    // 'Grades' row, or already gone).
                                    openAlert(t('error_title'), t('error_generic'));
                                }
                            }).catch(function(err) {
                                openAlert(t('error_title'), ((err && err.message) ? err.message : t('error_generic')));
                            }).then(function() {
                                busy = false;
                            });
                    });
                    return;
                }
                el = el.parentNode;
            }
        });

        // T3b: clear the whole history (reinforced, permanent confirmation).
        els.histClear.addEventListener('click', function() {
            if (els.histClear.disabled || hist.total === 0) {
                return;
            }
            openConfirm(t('hist_clear_title'), t('hist_clear_body'), function() {
                if (busy) {
                    return;
                }
                busy = true;
                els.histClear.disabled = true;
                callWS('block_mwa_dashboard_clear_grades_mail_history', {
                    courseid: courseid,
                    groupid: groupid
                }).then(function(res) {
                    var n = (res && typeof res.deleted === 'number') ? res.deleted : 0;
                    hist.rows = [];
                    hist.total = 0;
                    hist.offset = 0;
                    renderHistory();
                    openAlert(t('hist_clear_title'), t('hist_clear_done').replace('{count}', String(n)));
                }).catch(function(err) {
                    openAlert(t('error_title'), ((err && err.message) ? err.message : t('error_generic')));
                }).then(function() {
                    busy = false;
                    els.histClear.disabled = (hist.total === 0);
                });
            });
        });
    };

    // ---------- Data loading (get_grades + get_grades_send_options in parallel). ----------

    var parseStudents = function(res) {
        var rows = [];
        try {
            rows = JSON.parse(res.grades || '[]');
        } catch (err) {
            rows = [];
        }
        rawRows = [];
        (rows || []).forEach(function(r) {
            if (r && r.__mwa_type__ === 'student' && parseInt(r['User ID'], 10) > 0) {
                rawRows.push({
                    userid: parseInt(r['User ID'], 10),
                    name: nameOf(r),
                    total: r['Course total (Grade)'],
                    totalmax: r['Course total max (Grade)'],
                    raw: r
                });
            }
        });
    };

    var finalizeStudents = function() {
        students = [];
        rawRows.forEach(function(s) {
            var acts = {};
            activities.forEach(function(a) {
                acts[a.seq] = s.raw['act_' + a.seq];
            });
            students.push({
                userid: s.userid,
                name: s.name,
                total: s.total,
                totalmax: s.totalmax,
                acts: acts
            });
        });
        rawRows = [];
    };

    var checkReady = function() {
        if (uiBuilt || !studentsReady || !metaReady) {
            return;
        }
        uiBuilt = true;
        finalizeStudents();
        buildUI();
        bindEls();
        buildModal();
        populateActivities();
        buildRows();
        bindEvents();
        if (loadError) {
            openAlert(t('error_title'), t('error_generic'));
        }
    };

    var loadInitial = function() {
        callWS('block_mwa_dashboard_get_grades', {
            courseid: courseid,
            groupid: groupid,
            // T6 (grades-send flow): include items hidden in the gradebook so
            // the roster/preview match what can actually be selected below.
            includehidden: true
        }).then(function(res) {
            parseStudents(res);
        }).catch(function() {
            loadError = true;
        }).then(function() {
            studentsReady = true;
            checkReady();
        });
        callWS('block_mwa_dashboard_get_grades_send_options', {
            courseid: courseid,
            groupid: groupid
        }).then(function(res) {
            activities = (res && res.activities) ? res.activities : [];
        }).catch(function() {
            loadError = true;
        }).then(function() {
            metaReady = true;
            checkReady();
        });
    };

    var init = function(params) {
        params = params || {};
        courseid = params.courseid ? parseInt(params.courseid, 10) : 0;
        groupid = params.groupid ? parseInt(params.groupid, 10) : 0;
        // The localised UI strings arrive via window.MWA_GRADEMAIL_STR (inline
        // JSON rendered by enviarnotas.php). The params.str fallback keeps
        // direct / legacy calls working too.
        str = (typeof window.MWA_GRADEMAIL_STR === 'object' && window.MWA_GRADEMAIL_STR)
            ? window.MWA_GRADEMAIL_STR
            : (params.str || {});

        var root = document.getElementById('mwaGradesMailRoot');
        if (!root) {
            return;
        }
        els.wrap = root;
        loadInitial();
    };

    return {init: init};
});
