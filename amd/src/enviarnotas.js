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
 * "Send grades" page: student list, multiple selection, exact email preview
 * and batch send (email + Moodle message) with per-student results.
 *
 * All the text is server-rendered: the only data read from
 * block_mwa_dashboard_get_grades is the row of each listed student, and the
 * preview/send web services build the exact same message on the server
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
    // Mirrors the working block_mwa_dashboard/dashboard module: Ajax.call([...])[0] is a
    // promise that resolves to the external function's return value.
    var callWS = function(methodname, args) {
        var calls = Ajax.call([{
            methodname: methodname,
            args: args || {}
        }]);
        return calls[0];
    };

    var courseid = 0;
    var groupid = 0;
    var str = {};
    var students = [];
    var busy = false;
    var els = {};

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
        return value !== null && value !== undefined && value !== '' && isFinite(Number(value));
    };

    var buildUI = function() {
        els.wrap.innerHTML =
            '<div class="mwa-gm-wrap">' +
            '  <section class="mwa-gm-card">' +
            '    <div class="mwa-gm-cardhead">' +
            '      <h2>' + esc(t('students')) + '</h2>' +
            '      <span class="mwa-gm-count" id="mwaGmCount"></span>' +
            '    </div>' +
            '    <div class="mwa-gm-actions">' +
            '      <button type="button" class="mwa-gm-btn mwa-gm-btn-sec" id="mwaGmSelectAll">' + esc(t('selectall')) + '</button>' +
            '      <button type="button" class="mwa-gm-btn mwa-gm-btn-sec" id="mwaGmDeselectAll">' + esc(t('deselectall')) + '</button>' +
            '    </div>' +
            '    <table class="mwa-gm-table" id="mwaGmTable">' +
            '      <thead><tr>' +
            '        <th class="mwa-gm-check"></th>' +
            '        <th>' + esc(t('col_student')) + '</th>' +
            '        <th class="num">' + esc(t('col_grade')) + '</th>' +
            '      </tr></thead>' +
            '      <tbody id="mwaGmRows"></tbody>' +
            '    </table>' +
            '    <p class="mwa-gm-empty" id="mwaGmEmpty" hidden>' + esc(t('nostudents')) + '</p>' +
            '  </section>' +
            '  <section class="mwa-gm-card">' +
            '    <label class="mwa-gm-label" for="mwaGmComment">' + esc(t('comment')) + '</label>' +
            '    <textarea id="mwaGmComment" class="mwa-gm-textarea" rows="3" placeholder="' + esc(t('commentplaceholder')) + '"></textarea>' +
            '  </section>' +
            '  <div class="mwa-gm-actions mwa-gm-actions-end">' +
            '    <button type="button" class="mwa-gm-btn mwa-gm-btn-sec" id="mwaGmPreviewBtn">' + esc(t('preview')) + '</button>' +
            '    <button type="button" class="mwa-gm-btn mwa-gm-btn-primary" id="mwaGmSendBtn">' + esc(t('send')) + '</button>' +
            '  </div>' +
            '  <section class="mwa-gm-card" id="mwaGmPreviewCard" hidden>' +
            '    <div class="mwa-gm-cardhead">' +
            '      <h2>' + esc(t('previewtitle')) + '</h2>' +
            '      <span class="mwa-gm-student" id="mwaGmPreviewStudent"></span>' +
            '    </div>' +
            '    <p class="mwa-gm-subjline"><span class="mwa-gm-subjlabel">' + esc(t('previewsubject')) + ':</span> ' +
            '      <span id="mwaGmPreviewSubject"></span></p>' +
            '    <div class="mwa-gm-mail" id="mwaGmPreviewBody"></div>' +
            '  </section>' +
            '  <section class="mwa-gm-card" id="mwaGmResultsCard" hidden>' +
            '    <h2>' + esc(t('results')) + '</h2>' +
            '    <p class="mwa-gm-summary" id="mwaGmResultsSummary"></p>' +
            '    <table class="mwa-gm-table">' +
            '      <thead><tr>' +
            '        <th>' + esc(t('col_student')) + '</th>' +
            '        <th>' + esc(t('results')) + '</th>' +
            '        <th class="num">' + esc(t('channel_email')) + '</th>' +
            '        <th class="num">' + esc(t('channel_moodle')) + '</th>' +
            '      </tr></thead>' +
            '      <tbody id="mwaGmResultsRows"></tbody>' +
            '    </table>' +
            '  </section>' +
            '</div>';
    };

    var bindEls = function(root) {
        els.count = root.querySelector('#mwaGmCount');
        els.table = root.querySelector('#mwaGmTable');
        els.rows = root.querySelector('#mwaGmRows');
        els.empty = root.querySelector('#mwaGmEmpty');
        els.selectAll = root.querySelector('#mwaGmSelectAll');
        els.deselectAll = root.querySelector('#mwaGmDeselectAll');
        els.comment = root.querySelector('#mwaGmComment');
        els.previewBtn = root.querySelector('#mwaGmPreviewBtn');
        els.sendBtn = root.querySelector('#mwaGmSendBtn');
        els.previewCard = root.querySelector('#mwaGmPreviewCard');
        els.previewStudent = root.querySelector('#mwaGmPreviewStudent');
        els.previewSubject = root.querySelector('#mwaGmPreviewSubject');
        els.previewBody = root.querySelector('#mwaGmPreviewBody');
        els.resultsCard = root.querySelector('#mwaGmResultsCard');
        els.resultsSummary = root.querySelector('#mwaGmResultsSummary');
        els.resultsRows = root.querySelector('#mwaGmResultsRows');
    };

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

    var renderStudentList = function() {
        var html = '';
        students.forEach(function(s) {
            html += '<tr>' +
                '<td class="mwa-gm-check"><input type="checkbox" class="mwa-gm-checkbox" data-uid="' + s.userid + '" /></td>' +
                '<td>' + esc(s.name) + '</td>' +
                '<td class="num">' + gradeCell(s) + '</td>' +
                '</tr>';
        });
        els.rows.innerHTML = html;
        els.empty.hidden = (students.length > 0);
        els.table.style.display = (students.length > 0) ? '' : 'none';
        updateCount();
    };

    var loadStudents = function() {
        if (courseid <= 0) {
            renderStudentList();
            return;
        }
        callWS('block_mwa_dashboard_get_grades', {courseid: courseid, groupid: groupid}).then(function(res) {
            var rows = [];
            try {
                rows = JSON.parse(res.grades || '[]');
            } catch (e) {
                rows = [];
            }
            students = [];
            (rows || []).forEach(function(r) {
                if (r && r.__mwa_type__ === 'student' && parseInt(r['User ID'], 10) > 0) {
                    students.push({
                        userid: parseInt(r['User ID'], 10),
                        name: nameOf(r),
                        total: r['Course total (Grade)'],
                        totalmax: r['Course total max (Grade)']
                    });
                }
            });
            renderStudentList();
        }).catch(function() {
            showNotice(t('error_generic'));
        });
    };

    var showNotice = function(message) {
        var note = document.createElement('div');
        note.className = 'mwa-gm-error';
        note.setAttribute('role', 'alert');
        note.textContent = message;
        els.wrap.insertBefore(note, els.wrap.firstChild);
        window.setTimeout(function() {
            if (note.parentNode) {
                note.parentNode.removeChild(note);
            }
        }, 8000);
    };

    var doPreview = function() {
        if (busy) {
            return;
        }
        var ids = selectedIds();
        if (!ids.length) {
            window.alert(t('noselection'));
            return;
        }
        busy = true;
        els.previewBtn.disabled = true;
        callWS('block_mwa_dashboard_get_grades_mail_preview', {
            courseid: courseid,
            userid: ids[0],
            groupid: groupid,
            comment: (els.comment.value || '').trim()
        }).then(function(res) {
            els.previewStudent.textContent = res.studentname || '';
            els.previewSubject.textContent = res.subject || '';
            els.previewBody.innerHTML = res.html || '';
            els.previewCard.hidden = false;
        }).catch(function(err) {
            showNotice((err && err.message) ? err.message : t('error_generic'));
        }).then(function() {
            busy = false;
            els.previewBtn.disabled = false;
        });
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
            html += '<tr>' +
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
        els.resultsCard.hidden = false;
    };

    var doSend = function() {
        if (busy) {
            return;
        }
        var ids = selectedIds();
        if (!ids.length) {
            window.alert(t('noselection'));
            return;
        }
        if (!window.confirm(t('confirm_send'))) {
            return;
        }
        busy = true;
        els.sendBtn.disabled = true;
        els.sendBtn.textContent = t('sending');
        callWS('block_mwa_dashboard_send_grades_mail', {
            courseid: courseid,
            userids: ids,
            groupid: groupid,
            comment: (els.comment.value || '').trim()
        }).then(function(res) {
            renderResults(res);
        }).catch(function(err) {
            showNotice((err && err.message) ? err.message : t('error_generic'));
        }).then(function() {
            busy = false;
            els.sendBtn.disabled = false;
            els.sendBtn.textContent = t('send');
        });
    };

    var init = function(params) {
        params = params || {};
        courseid = params.courseid ? parseInt(params.courseid, 10) : 0;
        groupid = params.groupid ? parseInt(params.groupid, 10) : 0;
        str = params.str || {};

        var root = document.getElementById('mwaGradesMailRoot');
        if (!root) {
            return;
        }
        els.wrap = root;
        buildUI();
        bindEls(root);

        els.selectAll.addEventListener('click', function() {
            checkboxes().forEach(function(box) {
                box.checked = true;
            });
            updateCount();
        });
        els.deselectAll.addEventListener('click', function() {
            checkboxes().forEach(function(box) {
                box.checked = false;
            });
            updateCount();
        });
        els.rows.addEventListener('change', function(e) {
            var target = e.target;
            if (target && target.className && target.className.indexOf('mwa-gm-checkbox') !== -1) {
                updateCount();
            }
        });
        els.previewBtn.addEventListener('click', doPreview);
        els.sendBtn.addEventListener('click', doSend);

        loadStudents();
    };

    return {init: init};
});
