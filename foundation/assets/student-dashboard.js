(function () {
    'use strict';

    function readJSON(key, fallback) {
        try {
            var value = JSON.parse(localStorage.getItem(key) || '');
            return value == null ? fallback : value;
        } catch (error) {
            return fallback;
        }
    }

    function escapeHTML(value) {
        return String(value || '').replace(/[&<>"']/g, function (character) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[character];
        });
    }

    function newerLesson(localLesson, serverLesson) {
        if (!localLesson) return serverLesson || {};
        if (!serverLesson) return localLesson;
        return String(serverLesson.updatedAt || '') > String(localLesson.updatedAt || '') ? Object.assign({}, localLesson, serverLesson) : Object.assign({}, serverLesson, localLesson);
    }

    function mergedActivity(dashboard) {
        var local = window.MathBinderStudentActivity ? window.MathBinderStudentActivity.read() : readJSON('mathbinder_student_activity_v1', {lessons:{}});
        var server = {lessons:{}};
        var node = dashboard.querySelector('[data-mb-server-activity]');
        try { if (node) server = JSON.parse(node.textContent || '{}'); } catch (error) {}
        local = local && typeof local === 'object' ? local : {lessons:{}};
        server = server && typeof server === 'object' ? server : {lessons:{}};
        local.lessons = local.lessons || {}; server.lessons = server.lessons || {};
        var merged = {version:1,lastLessonId:local.lastLessonId || server.lastLessonId || '',lessons:{}};
        Object.keys(server.lessons).concat(Object.keys(local.lessons)).filter(function(id,index,all){ return all.indexOf(id) === index; }).forEach(function(id){ merged.lessons[id] = newerLesson(local.lessons[id], server.lessons[id]); });
        return merged;
    }

    document.addEventListener('DOMContentLoaded', function () {
        var dashboard = document.querySelector('[data-mb-dashboard="student"]');
        if (!dashboard) return;

        var activity = mergedActivity(dashboard);
        var lessons = activity && activity.lessons ? activity.lessons : {};
        var completed = new Set();
        var noteCount = 0;
        Object.keys(lessons).forEach(function (id) {
            if (lessons[id].completed) completed.add(String(id));
            if (lessons[id].hasNotes) noteCount++;
        });

        var saved = readJSON('mathbinder_saved_items_v2', []);
        if (!Array.isArray(saved)) saved = [];
        var favorites = readJSON('mathbinder_favorites', []);
        if (!Array.isArray(favorites)) favorites = [];
        var last = activity && activity.lastLessonId ? lessons[String(activity.lastLessonId)] : readJSON('mathbinder_last_lesson', null);
        var mastered = Object.keys(lessons).map(function(id){ return lessons[id]; }).filter(function(lesson){ return !!lesson.masteryPassed; });
        var scores = Object.keys(lessons).map(function(id){ return Number(lessons[id].masteryScore || 0); }).filter(function(score){ return score > 0; });
        var legacyBadges = readJSON('mathbinder_mastery_badges', []); if (!Array.isArray(legacyBadges)) legacyBadges = [];
        var badgeMap = {}; legacyBadges.forEach(function(badge){ badgeMap[String(badge.postId || badge.title || '')] = badge; });
        mastered.forEach(function(lesson){ badgeMap[String(lesson.id)] = {postId:lesson.id,title:lesson.title || 'MathBinder Master',earned:lesson.masteryUpdatedAt || lesson.updatedAt}; });
        var badges = Object.keys(badgeMap).filter(Boolean).map(function(key){ return badgeMap[key]; });

        var completedNode = dashboard.querySelector('[data-mb-student-completed]');
        var notesNode = dashboard.querySelector('[data-mb-student-notes]');
        var savedNode = dashboard.querySelector('[data-mb-student-saved]');
        var favoritesNode = dashboard.querySelector('[data-mb-student-favorites]');
        if (completedNode) completedNode.textContent = completed.size;
        if (notesNode) notesNode.textContent = noteCount;
        if (savedNode) savedNode.textContent = saved.length;
        if (favoritesNode) favoritesNode.textContent = favorites.length;
        var masteredNode = dashboard.querySelector('[data-mb-mastered-count]');
        var averageNode = dashboard.querySelector('[data-mb-mastery-average]');
        var badgeCountNode = dashboard.querySelector('[data-mb-badge-count]');
        var badgePreview = dashboard.querySelector('[data-mb-badge-preview]');
        if (masteredNode) masteredNode.textContent = mastered.length;
        if (averageNode) averageNode.textContent = scores.length ? ('Average mastery score: ' + Math.round(scores.reduce(function(sum,score){ return sum + score; },0) / scores.length) + '%') : 'No mastery checks completed yet.';
        if (badgeCountNode) badgeCountNode.textContent = badges.length;
        if (badgePreview && badges.length) badgePreview.innerHTML = badges.slice(-3).reverse().map(function(badge){ return '<span class="mb-earned-badge"><b aria-hidden="true">★</b>' + escapeHTML(badge.title || 'MathBinder Master') + '</span>'; }).join('');

        Array.prototype.slice.call(dashboard.querySelectorAll('[data-mb-assignment]')).forEach(function(card){
            var ids = String(card.getAttribute('data-lesson-ids') || '').split(',').filter(Boolean);
            var done = ids.filter(function(id){ return completed.has(String(id)); }).length;
            var percentDone = ids.length ? Math.round(done / ids.length * 100) : 0;
            var label = card.querySelector('[data-mb-assignment-progress]'); var fill = card.querySelector('[data-mb-assignment-fill]'); var link = card.querySelector('[data-mb-assignment-link]');
            if (label) label.textContent = done + ' of ' + ids.length + ' complete'; if (fill) fill.style.width = percentDone + '%';
            if (link && ids.length && done === ids.length) { link.textContent = 'Assignment complete ✓'; link.classList.add('is-complete'); }
            else if (link && done) link.firstChild.nodeValue = 'Continue assignment ';
        });

        var decorationForm = dashboard.querySelector('[data-mb-decoration-form]');
        var binderPreview = dashboard.querySelector('[data-mb-binder-preview]');
        function updateBinderPreview() {
            if (!decorationForm || !binderPreview) return;
            var titleField = decorationForm.querySelector('[name="binder_title"]');
            var themeField = decorationForm.querySelector('[name="binder_theme"]:checked');
            var stickers = Array.prototype.slice.call(decorationForm.querySelectorAll('[name="binder_stickers[]"]:checked')).map(function(field){ return field.value; });
            binderPreview.setAttribute('data-theme', themeField ? themeField.value : 'teal');
            var titleNode = binderPreview.querySelector('[data-mb-preview-title]'); if (titleNode) titleNode.textContent = titleField && titleField.value.trim() ? titleField.value.trim() : 'My MathBinder';
            var stickerNode = binderPreview.querySelector('[data-mb-preview-stickers]'); if (stickerNode) stickerNode.textContent = stickers.join(' ');
        }
        if (decorationForm) {
            decorationForm.addEventListener('input', updateBinderPreview); updateBinderPreview();
            decorationForm.addEventListener('submit', function(event){
                event.preventDefault(); var statusNode = dashboard.querySelector('[data-mb-decoration-status]');
                var titleField = decorationForm.querySelector('[name="binder_title"]'); var themeField = decorationForm.querySelector('[name="binder_theme"]:checked');
                var payload = {title:titleField ? titleField.value : 'My MathBinder',theme:themeField ? themeField.value : 'teal',stickers:Array.prototype.slice.call(decorationForm.querySelectorAll('[name="binder_stickers[]"]:checked')).map(function(field){ return field.value; })};
                if (statusNode) statusNode.textContent = 'Saving…';
                fetch(decorationForm.getAttribute('data-preferences-url'), {method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-WP-Nonce':decorationForm.getAttribute('data-rest-nonce')},body:JSON.stringify(payload)}).then(function(response){ if (!response.ok) throw new Error('save'); return response.json(); }).then(function(){ if (statusNode) statusNode.textContent = 'Saved ✓'; }).catch(function(){ if (statusNode) statusNode.textContent = 'Could not save'; });
            });
        }

        var joinForm = dashboard.querySelector('[data-mb-join-class]');
        if (joinForm) joinForm.addEventListener('submit', function(event){
            event.preventDefault();
            var field = joinForm.querySelector('[name="class_code"]');
            var status = joinForm.querySelector('[data-mb-join-status]');
            if (status) status.textContent = 'Joining class…';
            fetch(joinForm.getAttribute('data-join-url'), {method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-WP-Nonce':joinForm.getAttribute('data-rest-nonce')},body:JSON.stringify({class_code:field ? field.value : ''})})
                .then(function(response){ return response.json().then(function(payload){ if (!response.ok) throw new Error(payload && payload.message ? payload.message : 'Could not join the class.'); return payload; }); })
                .then(function(payload){ if (status) status.textContent = 'Joined ' + escapeHTML(payload.data.class_name) + ' ✓ Reloading assignments…'; window.setTimeout(function(){ window.location.reload(); }, 700); })
                .catch(function(error){ if (status) status.textContent = error.message || 'Could not join the class.'; });
        });

        var total = Math.max(completed.size, 1);
        var progressPageCount = parseInt(dashboard.getAttribute('data-mb-total-pages') || '0', 10);
        if (progressPageCount > 0) total = progressPageCount;
        var percent = progressPageCount > 0 ? Math.min(100, Math.round((completed.size / total) * 100)) : 0;
        var percentNode = dashboard.querySelector('[data-mb-student-percent]');
        var progress = dashboard.querySelector('[data-mb-student-progress]');
        if (percentNode) percentNode.textContent = percent + '%';
        if (progress) {
            progress.setAttribute('aria-valuenow', String(percent));
            var fill = progress.querySelector('span');
            if (fill) fill.style.width = percent + '%';
        }

        var pathSteps = Array.prototype.slice.call(dashboard.querySelectorAll('[data-mb-path-step]'));
        var firstIncomplete = -1;
        pathSteps.forEach(function (step, index) {
            if (firstIncomplete < 0 && !completed.has(String(step.getAttribute('data-lesson-id')))) firstIncomplete = index;
        });
        pathSteps.forEach(function (step, index) {
            var isDone = completed.has(String(step.getAttribute('data-lesson-id')));
            var status = step.querySelector('[data-mb-path-status]');
            step.classList.toggle('is-complete', isDone);
            step.classList.toggle('is-current', !isDone && index === firstIncomplete);
            step.classList.toggle('is-up-next', !isDone && index > firstIncomplete);
            if (status) status.textContent = isDone ? 'Completed' : (index === firstIncomplete ? 'Current' : 'Up Next');
        });
        var pathSummary = dashboard.querySelector('[data-mb-path-summary]');
        if (pathSummary) {
            pathSummary.textContent = firstIncomplete < 0 && pathSteps.length ? 'Path completed' :
                (pathSteps.length ? (completed.size + ' of ' + pathSteps.length + ' completed') : 'Choose your first lesson');
        }

        if (!last || !last.url) return;
        var title = last.title || 'Continue your last lesson';
        var nextTitle = dashboard.querySelector('[data-mb-student-next-title]');
        var notebookTitle = dashboard.querySelector('[data-mb-student-notebook-title]');
        var nextLink = dashboard.querySelector('[data-mb-student-next-link]');
        if (nextTitle) nextTitle.textContent = title;
        if (notebookTitle) notebookTitle.textContent = last.section || 'Your Learning';
        if (nextLink) {
            nextLink.href = last.url;
            nextLink.firstChild.nodeValue = 'Continue Learning ';
        }

        var recent = dashboard.querySelector('[data-mb-student-recent]');
        if (recent) {
            recent.innerHTML = '<article><span class="mb-task-number">1</span><div><small>' +
                escapeHTML(last.section || 'Recent lesson') + '</small><h3><a href="' +
                escapeHTML(last.url) + '">' + escapeHTML(title) + '</a></h3></div><span class="mb-task-status">Continue</span></article>';
        }
    });
}());
