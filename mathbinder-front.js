
(function(){
    const input = document.getElementById('mb-home-search-field');
    const results = document.getElementById('mb-search-results');
    if (!input || !results || typeof MathBinderSearch === 'undefined') return;

    let timer;
    const escapeHTML = (value) => String(value || '').replace(/[&<>"']/g, ch => ({
        '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
    }[ch]));

    function closeResults() {
        results.innerHTML = '';
        results.classList.remove('is-open');
    }

    input.addEventListener('input', function(){
        clearTimeout(timer);
        const q = input.value.trim();
        if (q.length < 2) {
            closeResults();
            return;
        }
        timer = setTimeout(async function(){
            const url = new URL(MathBinderSearch.ajaxUrl);
            url.searchParams.set('action', 'mb_topic_search');
            url.searchParams.set('nonce', MathBinderSearch.nonce);
            url.searchParams.set('q', q);
            try {
                const response = await fetch(url.toString(), {credentials:'same-origin'});
                const payload = await response.json();
                const items = payload && payload.success ? payload.data : [];
                if (!items.length) {
                    results.innerHTML = '<div class="mb-search-empty">No published Binder Page matches yet.</div>';
                } else {
                    results.innerHTML = items.map(item => `
                        <a class="mb-search-result" role="option" href="${escapeHTML(item.url)}">
                            <span class="mb-search-result-title">${escapeHTML(item.title)}</span>
                            <span class="mb-search-result-section">${escapeHTML(item.section)}</span>
                            ${item.summary ? `<span class="mb-search-result-summary">${escapeHTML(item.summary)}</span>` : ''}
                        </a>
                    `).join('');
                }
                results.classList.add('is-open');
            } catch (error) {
                closeResults();
            }
        }, 220);
    });

    document.addEventListener('click', function(event){
        if (!results.contains(event.target) && event.target !== input) closeResults();
    });
    input.addEventListener('keydown', function(event){
        if (event.key === 'Escape') closeResults();
    });
})();


(function(){
    const items = document.querySelectorAll('.mb-reveal');
    if (!items.length) return;
    if (!('IntersectionObserver' in window)) {
        items.forEach(item => item.classList.add('is-visible'));
        return;
    }
    const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, {threshold:0.12});
    items.forEach(item => observer.observe(item));
})();


(function(){
    const hero = document.querySelector('.mb-home-hero-v7');
    const image = hero ? hero.querySelector('.mb-home-hero-image img') : null;
    const card = hero ? hero.querySelector('.mb-home-hero-overlay') : null;

    if (hero && image && card && window.matchMedia('(pointer:fine)').matches) {
        hero.addEventListener('mousemove', function(event){
            const rect = hero.getBoundingClientRect();
            const x = (event.clientX - rect.left) / rect.width - .5;
            const y = (event.clientY - rect.top) / rect.height - .5;
            image.style.transform = `translate3d(${x * 5}px, ${y * 4}px, 0) scale(1.01)`;
            card.style.transform = `translate3d(${x * 2}px, ${y * 2}px, 0)`;
        });
        hero.addEventListener('mouseleave', function(){
            image.style.transform = '';
            card.style.transform = '';
        });
    }

    document.querySelectorAll('.mb-section-card').forEach(function(card){
        card.addEventListener('mouseenter', function(){
            card.classList.add('is-tab-active');
        });
        card.addEventListener('mouseleave', function(){
            card.classList.remove('is-tab-active');
        });
    });
})();


(function(){
    const art = document.querySelector('.mb-v8-art');
    const binder = art ? art.querySelector('.mb-v8-binder') : null;
    const page = art ? art.querySelector('.mb-v8-page') : null;
    if (!art || !binder || !page || !window.matchMedia('(pointer:fine)').matches) return;

    art.addEventListener('mousemove', function(event){
        const rect = art.getBoundingClientRect();
        const x = (event.clientX - rect.left) / rect.width - .5;
        const y = (event.clientY - rect.top) / rect.height - .5;
        binder.style.transform = `translate3d(${x * 4}px, ${y * 3}px, 0)`;
        page.style.transform = `translate3d(${x * -3}px, ${y * -2}px, 0)`;
    });
    art.addEventListener('mouseleave', function(){
        binder.style.transform = '';
        page.style.transform = '';
    });
})();


(function(){
    function removeThemeFooterAfterMathBinder(){
        if (!document.body.classList.contains('home')) return;

        const customFooter = document.querySelector('.mb-home-footer');
        if (!customFooter) return;

        // Find the highest ancestor below BODY that contains the custom footer.
        let container = customFooter;
        while (container.parentElement && container.parentElement !== document.body) {
            container = container.parentElement;
        }

        // Remove only elements rendered after the MathBinder page container.
        let next = container.nextElementSibling;
        while (next) {
            const remove = next;
            next = next.nextElementSibling;
            const text = (remove.textContent || '').toLowerCase();
            const looksLikeThemeFooter =
                remove.matches('footer, #colophon, .site-footer, .site-info, [class*="footer"], [id*="footer"]') ||
                text.includes('lorem ipsum') ||
                text.includes('terms & conditions');

            if (looksLikeThemeFooter) remove.remove();
        }

        // Remove any nested imported theme footer outside our custom footer.
        document.querySelectorAll('footer, #colophon, .site-footer, .site-info').forEach(function(node){
            if (node === customFooter || customFooter.contains(node)) return;
            const text = (node.textContent || '').toLowerCase();
            if (text.includes('lorem ipsum') || text.includes('terms & conditions')) {
                node.remove();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', removeThemeFooterAfterMathBinder);
    } else {
        removeThemeFooterAfterMathBinder();
    }
})();


/* MathBinder 11.0 — official sitewide footer */
(function(){
    function applyMathBinderFooter(){
        if (!window.mathbinderFooterData) return;

        const d = window.mathbinderFooterData;
        let footer =
            document.querySelector('footer.site-footer') ||
            document.querySelector('#colophon') ||
            document.querySelector('.site-footer') ||
            document.querySelector('[class*="pagelayer-footer"]') ||
            document.querySelector('[data-template="footer"]');

        if (!footer) {
            const footers = document.querySelectorAll('footer');
            footer = footers.length ? footers[footers.length - 1] : null;
        }

        if (!footer) {
            footer = document.createElement('footer');
            document.body.appendChild(footer);
        }

        footer.id = 'mb-official-site-footer';
        footer.className = 'mb-official-site-footer';

        footer.innerHTML = `
            <div class="mb-official-footer-main">
                <div class="mb-official-footer-brand">
                    <a href="${d.home}">
                        <img src="${d.logo}" alt="MathBinder">
                    </a>
                    <p>Digital Student Binder</p>
                    <span>Find It. Learn It. Master It.</span>
                </div>
                <div class="mb-official-footer-links">
                    <h2>Explore</h2>
                    <a href="${d.binderTopics}">Binder Topics</a>
                    <a href="${d.parents}">Parents</a>
                    <a href="${d.teachers}">Teachers</a>
                    <a href="${d.about}">About</a>
                    <a href="${d.contact}">Contact</a>
                </div>
                <div class="mb-official-footer-purpose">
                    <h2>Our Purpose</h2>
                    <p>Make trustworthy math help easier to find, easier to understand, and easier to use.</p>
                </div>
            </div>
            <div class="mb-official-footer-bottom">
                <span>&copy; ${d.year} MathBinder</span>
                <span>Digital Student Binder</span>
            </div>
        `;

        document.querySelectorAll('.mb-home-footer, .mb-topics-footer').forEach(function(node){
            if (node !== footer) node.remove();
        });

        document.querySelectorAll('footer, #colophon, .site-footer').forEach(function(node){
            if (node === footer || footer.contains(node)) return;
            const text = (node.textContent || '').toLowerCase();
            if (text.includes('lorem ipsum') || text.includes('terms & conditions')) {
                node.remove();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', applyMathBinderFooter);
    } else {
        applyMathBinderFooter();
    }
    window.addEventListener('load', applyMathBinderFooter);
})();


/* MathBinder 11.1 — mastery answer controls */
document.addEventListener('click', function(event){
    const button = event.target.closest('.mb-reveal-answer');
    if (!button) return;

    const answer = button.nextElementSibling;
    if (!answer || !answer.classList.contains('mb-answer')) return;

    const opening = answer.hasAttribute('hidden');
    if (opening) {
        answer.removeAttribute('hidden');
        button.textContent = 'Hide answer';
        button.setAttribute('aria-expanded', 'true');
    } else {
        answer.setAttribute('hidden', '');
        button.textContent = 'Check answer';
        button.setAttribute('aria-expanded', 'false');
    }
});


/* MathBinder 11.2 — multiple choice and local progress */
document.addEventListener('click', function(event){
    const submit = event.target.closest('.mb-submit-choice');
    if (submit) {
        const card = submit.closest('.mb-mastery-question');
        const selected = card ? card.querySelector('input[type="radio"]:checked') : null;
        const feedback = card ? card.querySelector('.mb-choice-feedback') : null;

        if (!selected) {
            if (feedback) {
                feedback.textContent = 'Choose an answer first.';
                feedback.className = 'mb-choice-feedback is-warning';
            }
            return;
        }

        const correct = submit.dataset.correct;
        const isCorrect = selected.value === correct;

        card.querySelectorAll('.mb-choice').forEach(function(choice){
            choice.classList.remove('is-correct', 'is-incorrect');
            const input = choice.querySelector('input');
            if (input && input.value === correct) choice.classList.add('is-correct');
            if (input && input.checked && input.value !== correct) choice.classList.add('is-incorrect');
        });

        if (feedback) {
            feedback.textContent = isCorrect ? 'Correct! Excellent work.' : 'Not quite. Review the highlighted correct answer and try another problem.';
            feedback.className = 'mb-choice-feedback ' + (isCorrect ? 'is-correct' : 'is-incorrect');
        }
    }

    const complete = event.target.closest('.mb-mark-complete');
    if (complete) {
        const panel = complete.closest('.mb-progress-panel');
        if (!panel) return;

        const section = panel.dataset.section || 'mathbinder';
        const postId = panel.dataset.currentPost;
        const total = parseInt(panel.dataset.total || '1', 10);
        const storageKey = 'mathbinder_completed_' + section;
        let completed = [];

        try {
            completed = JSON.parse(localStorage.getItem(storageKey) || '[]');
        } catch (error) {
            completed = [];
        }

        if (!completed.includes(postId)) completed.push(postId);
        localStorage.setItem(storageKey, JSON.stringify(completed));

        const count = Math.min(completed.length, total);
        const fill = panel.querySelector('.mb-progress-fill');
        const status = panel.querySelector('.mb-progress-status');

        if (fill) fill.style.width = ((count / total) * 100) + '%';
        if (status) status.textContent = count + ' of ' + total + ' pages completed';
        complete.textContent = 'Page completed ✓';
        complete.classList.add('is-complete');
    }
});

document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.mb-progress-panel').forEach(function(panel){
        const section = panel.dataset.section || 'mathbinder';
        const postId = panel.dataset.currentPost;
        const total = parseInt(panel.dataset.total || '1', 10);
        const storageKey = 'mathbinder_completed_' + section;
        let completed = [];

        try {
            completed = JSON.parse(localStorage.getItem(storageKey) || '[]');
        } catch (error) {
            completed = [];
        }

        const count = Math.min(completed.length, total);
        const fill = panel.querySelector('.mb-progress-fill');
        const status = panel.querySelector('.mb-progress-status');
        const button = panel.querySelector('.mb-mark-complete');

        if (count > 0) {
            if (fill) fill.style.width = ((count / total) * 100) + '%';
            if (status) status.textContent = count + ' of ' + total + ' pages completed';
        }

        if (completed.includes(postId) && button) {
            button.textContent = 'Page completed ✓';
            button.classList.add('is-complete');
        }
    });
});


/* MathBinder 12.0 — Student Experience */
document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.mb-section-toggle').forEach(function(toggle){
        const targetId = toggle.getAttribute('aria-controls');
        const content = document.getElementById(targetId);
        if (!content) return;

        const expanded = toggle.getAttribute('aria-expanded') === 'true';
        content.hidden = !expanded;
        if (expanded) content.classList.add('is-open');

        toggle.addEventListener('click', function(){
            const isOpen = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', String(!isOpen));
            content.hidden = isOpen;
            content.classList.toggle('is-open', !isOpen);
        });
    });

    document.querySelectorAll('.mb-student-rail a').forEach(function(link){
        link.addEventListener('click', function(event){
            const target = document.querySelector(link.getAttribute('href'));
            if (!target) return;
            event.preventDefault();

            const toggle = target.querySelector('.mb-section-toggle');
            if (toggle && toggle.getAttribute('aria-expanded') !== 'true') {
                toggle.click();
            }

            target.scrollIntoView({behavior:'smooth', block:'start'});
        });
    });

    const observed = [];
    document.querySelectorAll('[data-section-link]').forEach(function(link){
        const id = link.dataset.sectionLink;
        const target = document.getElementById(id);
        if (target) observed.push({link:link, target:target});
    });

    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver(function(entries){
            entries.forEach(function(entry){
                if (!entry.isIntersecting) return;
                document.querySelectorAll('.mb-student-rail a').forEach(function(a){a.classList.remove('is-active');});
                const match = observed.find(function(item){return item.target === entry.target;});
                if (match) match.link.classList.add('is-active');
            });
        }, {rootMargin:'-25% 0px -60% 0px', threshold:0});

        observed.forEach(function(item){observer.observe(item.target);});
    }

    const quiz = document.querySelector('.mb-mastery-quiz');
    if (quiz) {
        const questions = Array.from(quiz.querySelectorAll('.mb-mastery-question'));
        const progress = document.querySelector('.mb-question-progress');

        function updateQuestionProgress(){
            let current = 1;
            questions.forEach(function(question, index){
                const rect = question.getBoundingClientRect();
                if (rect.top < window.innerHeight * .55) current = index + 1;
            });
            if (progress) progress.textContent = 'Question ' + current + ' of ' + questions.length;
        }

        window.addEventListener('scroll', updateQuestionProgress, {passive:true});
        updateQuestionProgress();
    }
});

document.addEventListener('click', function(event){
    const submit = event.target.closest('.mb-submit-choice');
    if (submit) {
        const card = submit.closest('.mb-mastery-question');
        const selected = card ? card.querySelector('input[type="radio"]:checked') : null;
        if (selected && selected.value === submit.dataset.correct) {
            card.classList.add('mb-celebrate');
            setTimeout(function(){ card.classList.remove('mb-celebrate'); }, 900);
        }
    }

    const complete = event.target.closest('.mb-mark-complete');
    if (complete) {
        const finish = document.querySelector('.mb-lesson-finish');
        if (finish) {
            finish.classList.add('is-complete');
            setTimeout(function(){
                finish.scrollIntoView({behavior:'smooth', block:'center'});
            }, 300);
        }
    }
});


/* MathBinder 12.1 — lesson rail polish */
document.addEventListener('DOMContentLoaded', function(){
    const rail = document.querySelector('.mb-student-rail');
    if (!rail) return;

    const links = Array.from(rail.querySelectorAll('a'));
    links.forEach(function(link, index){
        link.classList.toggle('is-first', index === 0);
        link.classList.toggle('is-last', index === links.length - 1);
    });
});


/* MathBinder 12.2 — sticky horizontal section navigation */
document.addEventListener('DOMContentLoaded', function(){
    const nav = document.querySelector('.mb-sticky-section-nav');
    if (!nav) return;

    const tabs = Array.from(nav.querySelectorAll('[data-section-tab]'));
    const sections = tabs
        .map(function(tab){
            return {
                tab: tab,
                section: document.getElementById(tab.dataset.sectionTab)
            };
        })
        .filter(function(item){ return item.section; });

    tabs.forEach(function(tab){
        tab.addEventListener('click', function(event){
            const target = document.getElementById(tab.dataset.sectionTab);
            if (!target) return;

            event.preventDefault();

            const toggle = target.querySelector('.mb-section-toggle');
            if (toggle && toggle.getAttribute('aria-expanded') !== 'true') {
                toggle.click();
            }

            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    function activate(tab){
        tabs.forEach(function(item){
            item.classList.toggle('is-active', item === tab);
        });
    }

    if (sections.length) activate(sections[0].tab);

    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver(function(entries){
            const visible = entries
                .filter(function(entry){ return entry.isIntersecting; })
                .sort(function(a, b){ return a.boundingClientRect.top - b.boundingClientRect.top; });

            if (!visible.length) return;

            const match = sections.find(function(item){
                return item.section === visible[0].target;
            });

            if (match) activate(match.tab);
        }, {
            rootMargin: '-18% 0px -65% 0px',
            threshold: 0
        });

        sections.forEach(function(item){
            observer.observe(item.section);
        });
    }
});


/* MathBinder 13.0 — student progress, favorites, resume, and dashboard */
(function(){
    const FAVORITES_KEY = 'mathbinder_favorites';
    const LAST_LESSON_KEY = 'mathbinder_last_lesson';

    function readJSON(key, fallback){
        try {
            const value = JSON.parse(localStorage.getItem(key) || '');
            return value == null ? fallback : value;
        } catch (error) {
            return fallback;
        }
    }

    function writeJSON(key, value){
        try {
            localStorage.setItem(key, JSON.stringify(value));
        } catch (error) {}
    }

    function favorites(){
        const stored = readJSON(FAVORITES_KEY, []);
        return Array.isArray(stored) ? stored : [];
    }

    function updateFavoriteButton(button){
        if (!button) return;
        const ids = favorites().map(function(item){ return String(item.id); });
        const saved = ids.includes(String(button.dataset.postId));
        button.classList.toggle('is-favorite', saved);
        button.setAttribute('aria-pressed', saved ? 'true' : 'false');
        button.textContent = saved ? '★ Favorite Saved' : '☆ Save Favorite';
    }

    document.addEventListener('DOMContentLoaded', function(){
        const progressPanel = document.querySelector('.mb-progress-panel');
        const favoriteButton = document.querySelector('.mb-favorite-page');

        if (progressPanel) {
            writeJSON(LAST_LESSON_KEY, {
                id: String(progressPanel.dataset.currentPost || ''),
                title: progressPanel.dataset.pageTitle || document.title,
                url: progressPanel.dataset.pageUrl || window.location.href,
                section: progressPanel.dataset.sectionTitle || ''
            });
        }

        updateFavoriteButton(favoriteButton);

        const dashboard = document.querySelector('.mb-progress-dashboard');
        if (!dashboard) return;

        const lessonCards = Array.from(dashboard.querySelectorAll('.mb-dashboard-lesson[data-post-id]'));
        const pageMap = {};
        lessonCards.forEach(function(card){
            pageMap[String(card.dataset.postId)] = {
                id: String(card.dataset.postId),
                title: card.dataset.title,
                url: card.dataset.url,
                section: card.dataset.sectionTitle,
                sectionSlug: card.dataset.sectionSlug
            };
        });

        let completedTotal = 0;
        dashboard.querySelectorAll('[data-mb-section-card]').forEach(function(card){
            const slug = card.dataset.section;
            const total = parseInt(card.dataset.total || '0', 10);
            const completed = readJSON('mathbinder_completed_' + slug, []).map(String);
            const count = Math.min(completed.length, total);
            completedTotal += count;

            const countNode = card.querySelector('[data-mb-section-count]');
            const fill = card.querySelector('[data-mb-section-fill]');
            if (countNode) countNode.textContent = count + ' / ' + total;
            if (fill) fill.style.width = (total ? (count / total) * 100 : 0) + '%';

            card.querySelectorAll('.mb-dashboard-lesson[data-post-id]').forEach(function(link){
                const done = completed.includes(String(link.dataset.postId));
                link.classList.toggle('is-complete', done);
                const state = link.querySelector('.mb-lesson-state');
                if (state) state.textContent = done ? '✓' : '○';
            });
        });

        const totalPages = lessonCards.length;
        const percent = totalPages ? Math.round((completedTotal / totalPages) * 100) : 0;
        const completedMetric = dashboard.querySelector('[data-mb-total-completed]');
        const percentMetric = dashboard.querySelector('[data-mb-overall-percent]');
        if (completedMetric) completedMetric.textContent = completedTotal;
        if (percentMetric) percentMetric.textContent = percent + '%';

        dashboard.querySelectorAll('[data-milestone]').forEach(function(badge){
            const threshold = parseInt(badge.dataset.milestone || '0', 10);
            badge.classList.toggle('is-earned', percent >= threshold);
        });

        const last = readJSON(LAST_LESSON_KEY, null);
        const resume = dashboard.querySelector('[data-mb-resume]');
        if (last && last.url && resume) {
            resume.hidden = false;
            const title = resume.querySelector('[data-mb-resume-title]');
            const section = resume.querySelector('[data-mb-resume-section]');
            const link = resume.querySelector('[data-mb-resume-link]');
            if (title) title.textContent = last.title || 'Resume your last Binder Page';
            if (section) section.textContent = last.section ? 'Continue in ' + last.section : '';
            if (link) link.href = last.url;
        }

        const savedFavorites = favorites();
        const favoritesPanel = dashboard.querySelector('[data-mb-favorites-panel]');
        const favoritesList = dashboard.querySelector('[data-mb-favorites-list]');
        const favoriteMetric = dashboard.querySelector('[data-mb-favorite-count]');
        if (favoriteMetric) favoriteMetric.textContent = savedFavorites.length;

        if (savedFavorites.length && favoritesPanel && favoritesList) {
            favoritesPanel.hidden = false;
            favoritesList.innerHTML = savedFavorites.map(function(item){
                const safeTitle = String(item.title || 'Binder Page').replace(/[&<>"']/g, function(ch){
                    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch];
                });
                const safeSection = String(item.section || '').replace(/[&<>"']/g, function(ch){
                    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch];
                });
                return '<a class="mb-dashboard-lesson mb-favorite-dashboard-card" href="' + item.url + '">' +
                    '<span class="mb-lesson-state">★</span><span><strong>' + safeTitle + '</strong>' +
                    (safeSection ? '<small>' + safeSection + '</small>' : '') + '</span></a>';
            }).join('');
        }
    });

    document.addEventListener('click', function(event){
        const favoriteButton = event.target.closest('.mb-favorite-page');
        if (favoriteButton) {
            const list = favorites();
            const id = String(favoriteButton.dataset.postId);
            const existing = list.findIndex(function(item){ return String(item.id) === id; });

            if (existing >= 0) {
                list.splice(existing, 1);
            } else {
                list.push({
                    id: id,
                    title: favoriteButton.dataset.title || 'Binder Page',
                    url: favoriteButton.dataset.url || window.location.href,
                    section: favoriteButton.dataset.section || ''
                });
            }

            writeJSON(FAVORITES_KEY, list);
            updateFavoriteButton(favoriteButton);
        }

        if (event.target.closest('.mb-print-page')) {
            window.print();
        }
    });
})();


/* MathBinder 14.0 — Interactive Student Workbook */
(function(){
    const STARTED_KEY = 'mathbinder_started_pages';

    function readJSON(key, fallback){
        try {
            const value = JSON.parse(localStorage.getItem(key) || '');
            return value == null ? fallback : value;
        } catch (error) {
            return fallback;
        }
    }

    function writeJSON(key, value){
        try {
            localStorage.setItem(key, JSON.stringify(value));
        } catch (error) {}
    }

    function workbookKey(postId){
        return 'mathbinder_workbook_' + postId;
    }

    function markStarted(postId){
        const started = readJSON(STARTED_KEY, []);
        const id = String(postId || '');
        if (id && !started.includes(id)) {
            started.push(id);
            writeJSON(STARTED_KEY, started);
        }
    }

    function wordCount(value){
        const clean = String(value || '').trim();
        return clean ? clean.split(/\s+/).length : 0;
    }

    function setSaveState(workbook, message){
        const node = workbook.querySelector('.mb-workbook-save-state');
        if (!node) return;
        node.textContent = message;
        node.classList.add('is-saved');
        clearTimeout(node._timer);
        node._timer = setTimeout(function(){
            node.classList.remove('is-saved');
            node.textContent = 'Saved locally';
        }, 1400);
    }

    function collectWorkbook(workbook){
        return {
            notes: workbook.querySelector('.mb-student-notes')?.value || '',
            confidence: workbook.querySelector('[data-confidence].is-selected')?.dataset.confidence || '',
            important: workbook.querySelector('.mb-reflection-important')?.value || '',
            question: workbook.querySelector('.mb-reflection-question')?.value || '',
            connection: workbook.querySelector('.mb-reflection-connection')?.value || '',
            updated: new Date().toISOString()
        };
    }

    function saveWorkbook(workbook, message){
        const postId = workbook.dataset.workbookPost;
        writeJSON(workbookKey(postId), collectWorkbook(workbook));
        markStarted(postId);
        setSaveState(workbook, message || 'Saved');
    }

    function hydrateWorkbook(workbook){
        const data = readJSON(workbookKey(workbook.dataset.workbookPost), {});
        const notes = workbook.querySelector('.mb-student-notes');
        if (notes) notes.value = data.notes || '';

        const count = workbook.querySelector('[data-note-count]');
        if (count) {
            const total = wordCount(data.notes);
            count.textContent = total + (total === 1 ? ' word' : ' words');
        }

        ['important','question','connection'].forEach(function(field){
            const input = workbook.querySelector('.mb-reflection-' + field);
            if (input) input.value = data[field] || '';
        });

        if (data.confidence) {
            const selected = workbook.querySelector('[data-confidence="' + data.confidence + '"]');
            if (selected) selected.classList.add('is-selected');
        }
    }

    document.addEventListener('DOMContentLoaded', function(){
        const workbook = document.querySelector('.mb-workbook-section');
        if (workbook) {
            markStarted(workbook.dataset.workbookPost);
            hydrateWorkbook(workbook);

            const notes = workbook.querySelector('.mb-student-notes');
            const count = workbook.querySelector('[data-note-count]');
            if (notes) {
                notes.addEventListener('input', function(){
                    const total = wordCount(notes.value);
                    if (count) count.textContent = total + (total === 1 ? ' word' : ' words');
                });
            }
        }

        const dashboard = document.querySelector('.mb-progress-dashboard');
        if (!dashboard) return;

        const started = readJSON(STARTED_KEY, []).map(String);

        dashboard.querySelectorAll('[data-mb-section-card]').forEach(function(sectionCard){
            const slug = sectionCard.dataset.section;
            const completed = readJSON('mathbinder_completed_' + slug, []).map(String);

            sectionCard.querySelectorAll('.mb-dashboard-lesson[data-post-id]').forEach(function(card){
                const id = String(card.dataset.postId);
                const state = card.querySelector('.mb-lesson-state');
                const title = card.querySelector('span:last-child');

                card.classList.remove('is-not-started','is-in-progress');
                if (completed.includes(id)) {
                    card.classList.add('is-complete');
                    if (state) state.textContent = '✓';
                    if (title && !title.querySelector('small')) {
                        title.insertAdjacentHTML('beforeend','<small>Completed</small>');
                    }
                } else if (started.includes(id)) {
                    card.classList.add('is-in-progress');
                    if (state) state.textContent = '→';
                    if (title && !title.querySelector('small')) {
                        title.insertAdjacentHTML('beforeend','<small>In Progress</small>');
                    }
                } else {
                    card.classList.add('is-not-started');
                    if (state) state.textContent = '○';
                    if (title && !title.querySelector('small')) {
                        title.insertAdjacentHTML('beforeend','<small>Not Started</small>');
                    }
                }
            });
        });

        const firstInProgress = dashboard.querySelector('.mb-dashboard-lesson.is-in-progress');
        const firstNotStarted = dashboard.querySelector('.mb-dashboard-lesson.is-not-started');
        const resume = dashboard.querySelector('[data-mb-resume]');

        if (resume && !firstInProgress && firstNotStarted) {
            resume.hidden = false;
            const title = resume.querySelector('[data-mb-resume-title]');
            const section = resume.querySelector('[data-mb-resume-section]');
            const link = resume.querySelector('[data-mb-resume-link]');

            if (title) title.textContent = 'Start ' + firstNotStarted.dataset.title;
            if (section) section.textContent = 'Next recommended lesson in ' + firstNotStarted.dataset.sectionTitle;
            if (link) {
                link.href = firstNotStarted.dataset.url;
                link.textContent = 'Start Lesson →';
            }
        }
    });

    document.addEventListener('click', function(event){
        const workbook = event.target.closest('.mb-workbook-section') || document.querySelector('.mb-workbook-section');

        if (false && event.target.closest('.mb-save-notes') && workbook) {
            saveWorkbook(workbook, 'Notes saved');
        }

        if (false && event.target.closest('.mb-save-reflection') && workbook) {
            saveWorkbook(workbook, 'Reflection saved');
        }

        const confidence = event.target.closest('[data-confidence]');
        if (confidence && workbook) {
            workbook.querySelectorAll('[data-confidence]').forEach(function(button){
                button.classList.remove('is-selected');
            });
            confidence.classList.add('is-selected');

            const messages = {
                '1':'That is okay—review Teach It and Watch It, then try again.',
                '2':'You are building understanding. Practice one more example.',
                '3':'Nice progress. Try explaining the idea in your own words.',
                '4':'Excellent. Teach the idea to someone else or complete the mastery check.'
            };
            const message = workbook.querySelector('.mb-confidence-message');
            if (message) message.textContent = messages[confidence.dataset.confidence] || '';
            saveWorkbook(workbook, 'Confidence saved');
        }

        if (false && event.target.closest('.mb-clear-notes') && workbook) {
            const notes = workbook.querySelector('.mb-student-notes');
            if (notes && window.confirm('Clear the notes for this Binder Page?')) {
                notes.value = '';
                const count = workbook.querySelector('[data-note-count]');
                if (count) count.textContent = '0 words';
                saveWorkbook(workbook, 'Notes cleared');
            }
        }

        if (event.target.closest('.mb-download-notes') && workbook) {
            const data = collectWorkbook(workbook);
            const title = workbook.dataset.workbookTitle || 'MathBinder Notes';
            const section = workbook.dataset.workbookSection || '';
            const content =
                title + '\n' +
                section + '\n' +
                '========================================\n\n' +
                'STUDENT NOTES\n' + (data.notes || 'No notes saved.') + '\n\n' +
                'CONFIDENCE: ' + (data.confidence || 'Not rated') + ' / 4\n\n' +
                'MOST IMPORTANT IDEA\n' + (data.important || '') + '\n\n' +
                'QUESTION I STILL HAVE\n' + (data.question || '') + '\n\n' +
                'REAL-WORLD CONNECTION\n' + (data.connection || '');

            const blob = new Blob([content], {type:'text/plain;charset=utf-8'});
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = title.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'') + '-notes.txt';
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(url);
        }
    });

    window.addEventListener('beforeunload', function(){
        const workbook = document.querySelector('.mb-workbook-section');
        if (workbook) {
            writeJSON(workbookKey(workbook.dataset.workbookPost), collectWorkbook(workbook));
        }
    });
})();


/* MathBinder 15.0 — My Math Journal autosave and print */
(function(){
    function readJSON(key, fallback){
        try {
            const value = JSON.parse(localStorage.getItem(key) || '');
            return value == null ? fallback : value;
        } catch (error) {
            return fallback;
        }
    }

    function writeJSON(key, value){
        try {
            localStorage.setItem(key, JSON.stringify(value));
        } catch (error) {}
    }

    function key(postId){
        return 'mathbinder_workbook_' + postId;
    }

    function getJournal(journal){
        return {
            notes: journal.querySelector('.mb-student-notes')?.value || '',
            confidence: journal.querySelector('[data-confidence].is-selected')?.dataset.confidence || '',
            important: journal.querySelector('.mb-reflection-important')?.value || '',
            question: journal.querySelector('.mb-reflection-question')?.value || '',
            connection: journal.querySelector('.mb-reflection-connection')?.value || '',
            updated: new Date().toISOString()
        };
    }

    function showStatus(journal, text){
        const status = journal.querySelector('.mb-workbook-save-state');
        if (!status) return;
        status.textContent = text;
        status.classList.add('is-saved');
        clearTimeout(status._journalTimer);
        status._journalTimer = setTimeout(function(){
            status.textContent = 'Saved automatically';
            status.classList.remove('is-saved');
        }, 1400);
    }

    function save(journal, message){
        writeJSON(key(journal.dataset.workbookPost), getJournal(journal));
        showStatus(journal, message || 'Saved');
    }

    function autosaveInput(journal, element){
        let timer;
        element.addEventListener('input', function(){
            clearTimeout(timer);
            const status = journal.querySelector('.mb-workbook-save-state');
            if (status) status.textContent = 'Saving…';
            timer = setTimeout(function(){
                save(journal, 'Saved');
            }, 700);
        });
    }

    function printableJournalHTML(journal){
        const data = getJournal(journal);
        const title = journal.dataset.workbookTitle || 'Math Journal';
        const section = journal.dataset.workbookSection || '';
        const confidenceLabels = {
            '1':'Still Learning',
            '2':'Getting There',
            '3':'Mostly Confident',
            '4':'I Could Teach This'
        };

        function safe(value){
            return String(value || '').replace(/[&<>"']/g, function(ch){
                return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch];
            }).replace(/\n/g,'<br>');
        }

        return '<!doctype html><html><head><meta charset="utf-8"><title>' + safe(title) + ' — My Math Journal</title>' +
            '<style>' +
            'body{font-family:Arial,sans-serif;color:#24323d;margin:40px;line-height:1.55}' +
            '.header{border:2px solid #0f8f8f;border-left:12px solid #0f8f8f;border-radius:16px;padding:24px;background:linear-gradient(135deg,#eefafa,#f7f1fc)}' +
            'h1{color:#54269a;margin:0 0 4px} h2{color:#54269a;border-bottom:2px solid #d9eeee;padding-bottom:7px;margin-top:28px}' +
            '.meta{color:#5b6972}.box{border:1px solid #cfdcdf;border-radius:12px;padding:16px;margin-top:12px;min-height:70px}' +
            '.notes{background:repeating-linear-gradient(to bottom,#fff 0,#fff 29px,#dff0f3 30px);line-height:30px;min-height:300px;padding:10px 16px}' +
            '.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.sticky{padding:16px;min-height:120px;border-radius:4px;box-shadow:0 4px 10px #ccc}.y{background:#fff6b7}.b{background:#dff2ff}.g{background:#def4df}' +
            '@media print{body{margin:20px}.no-print{display:none}}' +
            '</style></head><body>' +
            '<div class="header"><small>MY MATH JOURNAL</small><h1>' + safe(title) + '</h1><div class="meta">' + safe(section) + '</div></div>' +
            '<h2>Confidence</h2><div class="box">' + safe(confidenceLabels[data.confidence] || 'Not selected') + '</div>' +
            '<h2>Reflection</h2><div class="grid">' +
            '<div class="sticky y"><strong>Big Idea</strong><br>' + safe(data.important) + '</div>' +
            '<div class="sticky b"><strong>Question</strong><br>' + safe(data.question) + '</div>' +
            '<div class="sticky g"><strong>Real-World Connection</strong><br>' + safe(data.connection) + '</div></div>' +
            '<h2>My Math Notes</h2><div class="notes">' + safe(data.notes) + '</div>' +
            '<h2>Learning Record</h2>' +
            '<div class="box"><strong>Date:</strong> ' + new Date().toLocaleDateString() + '</div>' +
            '<div style="display:grid;grid-template-columns:1fr 1fr;gap:30px;margin-top:42px">' +
            '<div style="border-top:1px solid #24323d;padding-top:8px">Parent Signature</div>' +
            '<div style="border-top:1px solid #24323d;padding-top:8px">Teacher Signature</div></div>' +
            '<script>window.onload=function(){window.print();}</script>' +
            '</body></html>';
    }

    document.addEventListener('DOMContentLoaded', function(){
        const journal = document.querySelector('.mb-workbook-section');
        if (!journal) return;

        journal.querySelectorAll('.mb-student-notes, .mb-reflection-important, .mb-reflection-question, .mb-reflection-connection').forEach(function(element){
            autosaveInput(journal, element);
        });

        const status = journal.querySelector('.mb-workbook-save-state');
        if (status) status.textContent = 'Saved automatically';
    });

    document.addEventListener('click', function(event){
        const journal = event.target.closest('.mb-workbook-section') || document.querySelector('.mb-workbook-section');
        if (!journal) return;

        if (event.target.closest('.mb-print-journal')) {
            save(journal, 'Saved');
            const popup = window.open('', '_blank', 'width=900,height=800');
            if (popup) {
                popup.document.open();
                popup.document.write(printableJournalHTML(journal));
                popup.document.close();
            }
        }

        if (event.target.closest('.mb-clear-journal')) {
            if (!window.confirm('Clear all notes, reflections, and confidence for this Math Journal?')) return;

            journal.querySelectorAll('textarea, input[type="text"]').forEach(function(field){
                field.value = '';
            });
            journal.querySelectorAll('[data-confidence]').forEach(function(button){
                button.classList.remove('is-selected');
            });

            const count = journal.querySelector('[data-note-count]');
            if (count) count.textContent = '0 words';

            writeJSON(key(journal.dataset.workbookPost), {});
            showStatus(journal, 'Journal cleared');
        }
    });
})();


/* MathBinder 15.1 — celebration and polished learning record */
(function(){
    document.addEventListener('click', function(event){
        const confidence = event.target.closest('[data-confidence="4"]');
        if (!confidence) return;

        const journal = confidence.closest('.mb-workbook-section');
        if (!journal) return;

        const celebration = journal.querySelector('.mb-journal-celebration');
        if (!celebration) return;

        celebration.hidden = false;
        celebration.classList.remove('is-showing');
        void celebration.offsetWidth;
        celebration.classList.add('is-showing');

        clearTimeout(celebration._hideTimer);
        celebration._hideTimer = setTimeout(function(){
            celebration.classList.remove('is-showing');
            setTimeout(function(){
                celebration.hidden = true;
            }, 300);
        }, 2600);
    });
})();


/* MathBinder 17.0 — Your Growing MathBinder */
(function(){
    const COLLECTION_KEY = 'mathbinder_resource_collection';

    function readJSON(key, fallback){
        try {
            const value = JSON.parse(localStorage.getItem(key) || '');
            return value == null ? fallback : value;
        } catch (error) {
            return fallback;
        }
    }

    function writeJSON(key, value){
        try {
            localStorage.setItem(key, JSON.stringify(value));
        } catch (error) {}
    }

    function collection(){
        const stored = readJSON(COLLECTION_KEY, []);
        return Array.isArray(stored) ? stored : [];
    }

    function hasResource(postId, type){
        return collection().some(function(item){
            return String(item.postId) === String(postId) && item.type === type;
        });
    }

    function collectResource(section, type){
        const postId = String(section.dataset.resourcePost || '');
        if (!postId || !type) return;

        const list = collection();
        if (!list.some(function(item){
            return String(item.postId) === postId && item.type === type;
        })) {
            list.push({
                postId: postId,
                type: type,
                title: section.dataset.resourceTitle || 'Binder Page',
                section: section.dataset.resourceSection || '',
                collected: new Date().toISOString()
            });
            writeJSON(COLLECTION_KEY, list);
        }
    }

    function updateResourceCards(){
        const section = document.querySelector('.mb-binder-resources[data-resource-post]');
        if (!section) return;

        const postId = section.dataset.resourcePost;
        section.querySelectorAll('[data-resource-card]').forEach(function(card){
            const type = card.dataset.resourceCard;
            const collected = hasResource(postId, type);
            card.classList.toggle('is-collected', collected);

            const action = card.querySelector('[data-resource-action]');
            if (action && collected) {
                action.classList.add('is-collected-action');
                action.textContent = '✓ In Your Binder';
            }

            let badge = card.querySelector('.mb-in-binder-badge');
            if (collected && !badge) {
                badge = document.createElement('span');
                badge.className = 'mb-in-binder-badge';
                badge.textContent = '✓ In Your Binder';
                card.appendChild(badge);
            }
        });
    }

    function renderCollectionDashboard(){
        const dashboard = document.querySelector('.mb-collection-dashboard');
        if (!dashboard) return;

        const items = collection();
        const lessons = new Set(items.map(function(item){ return String(item.postId); }));
        const lessonCards = Array.from(dashboard.querySelectorAll('[data-collection-post]'));
        const totalPossible = lessonCards.length * 4;
        const percent = totalPossible ? Math.round((items.length / totalPossible) * 100) : 0;

        const completedIds = new Set();
        dashboard.querySelectorAll('[data-collection-section]').forEach(function(section){
            const slug = section.dataset.collectionSection;
            readJSON('mathbinder_completed_' + slug, []).forEach(function(id){
                completedIds.add(String(id));
            });
        });

        const totalNode = dashboard.querySelector('[data-mb-collected-total]');
        const lessonsNode = dashboard.querySelector('[data-mb-collected-lessons]');
        const completedNode = dashboard.querySelector('[data-mb-collection-completed]');
        const percentNode = dashboard.querySelector('[data-mb-collection-percent]');
        const fill = dashboard.querySelector('[data-mb-collection-fill]');

        if (totalNode) totalNode.textContent = items.length;
        if (lessonsNode) lessonsNode.textContent = lessons.size;
        if (completedNode) completedNode.textContent = completedIds.size;
        if (percentNode) percentNode.textContent = percent + '%';
        if (fill) fill.style.width = percent + '%';

        dashboard.querySelectorAll('[data-collection-post]').forEach(function(card){
            const postId = String(card.dataset.collectionPost);
            const types = ['notes','practice','challenge','support'];
            let count = 0;

            types.forEach(function(type){
                const token = card.querySelector('[data-resource-type="' + type + '"]');
                const collected = items.some(function(item){
                    return String(item.postId) === postId && item.type === type;
                });

                if (collected) count++;
                if (token) token.classList.toggle('is-collected', collected);
            });

            const countNode = card.querySelector('[data-lesson-resource-count]');
            if (countNode) countNode.textContent = count + ' / 4';
            card.classList.toggle('has-resources', count > 0);
            card.classList.toggle('is-complete-collection', count === 4);
        });

        dashboard.querySelectorAll('[data-collection-section]').forEach(function(section){
            const slug = section.dataset.collectionSection;
            const count = items.filter(function(item){ return item.section === slug; }).length;
            const countNode = section.querySelector('[data-section-resource-count]');
            if (countNode) countNode.textContent = count + (count === 1 ? ' resource' : ' resources');
        });
    }

    document.addEventListener('DOMContentLoaded', function(){
        updateResourceCards();
        renderCollectionDashboard();
    });

    document.addEventListener('click', function(event){
        const action = event.target.closest('[data-resource-action]');
        if (!action) return;

        const section = action.closest('.mb-binder-resources[data-resource-post]');
        if (!section) return;

        collectResource(section, action.dataset.resourceAction);
        setTimeout(updateResourceCards, 0);
    });
})();


/* MathBinder 18.0 — Gold Standard Learn It interactions */
document.addEventListener('click', function(event){
    const vocab = event.target.closest('.mb-vocab-toggle');
    if (vocab) {
        const definition = vocab.nextElementSibling;
        const open = vocab.getAttribute('aria-expanded') === 'true';
        vocab.setAttribute('aria-expanded', String(!open));
        if (definition) definition.hidden = open;
        const plus = vocab.querySelector('.mb-vocab-plus');
        if (plus) plus.textContent = open ? '+' : '−';
    }

    const nextStep = event.target.closest('.mb-show-next-step');
    if (nextStep) {
        const example = nextStep.closest('.mb-step-example');
        const hidden = example ? Array.from(example.querySelectorAll('.mb-example-step[hidden]')) : [];
        if (hidden.length) {
            hidden[0].hidden = false;
            hidden[0].classList.add('is-visible');
        }
        if (hidden.length <= 1) {
            nextStep.textContent = 'All Steps Shown ✓';
            nextStep.disabled = true;
        }
    }

    const choice = event.target.closest('.mb-learn-check-options button');
    if (choice) {
        const card = choice.closest('.mb-learn-check');
        const feedback = card ? card.querySelector('.mb-learn-check-feedback') : null;
        if (!card) return;

        card.querySelectorAll('.mb-learn-check-options button').forEach(function(button){
            button.classList.remove('is-correct','is-incorrect','is-selected');
        });

        choice.classList.add('is-selected');
        const correct = choice.dataset.choice === card.dataset.correct;
        choice.classList.add(correct ? 'is-correct' : 'is-incorrect');

        if (!correct) {
            const right = card.querySelector('[data-choice="' + card.dataset.correct + '"]');
            if (right) right.classList.add('is-correct');
        }

        if (feedback) {
            feedback.textContent = correct
                ? 'Correct! Explain how place value helped you decide.'
                : 'Not quite. Compare the digits from left to right and try to explain the correct choice.';
            feedback.className = 'mb-learn-check-feedback ' + (correct ? 'is-correct' : 'is-incorrect');
        }
    }
});


/* MathBinder 19.0 — Gold Standard Watch It */
(function(){
    function watchKey(postId){
        return 'mathbinder_watch_complete_' + postId;
    }

    function setWatchComplete(section, complete){
        const postId = section.dataset.watchPost;
        try {
            localStorage.setItem(watchKey(postId), complete ? '1' : '0');
        } catch (error) {}

        const status = section.querySelector('[data-watch-status]');
        const button = section.querySelector('.mb-mark-video-complete');

        section.classList.toggle('is-watch-complete', complete);
        if (status) status.textContent = complete ? 'Completed' : 'Not Started';
        if (button) button.textContent = complete ? 'Video Completed ✓' : 'Mark Video Complete';
    }

    function hydrateWatch(){
        const section = document.querySelector('.mb-gold-watch[data-watch-post]');
        if (!section) return;
        let complete = false;
        try {
            complete = localStorage.getItem(watchKey(section.dataset.watchPost)) === '1';
        } catch (error) {}
        setWatchComplete(section, complete);
    }

    document.addEventListener('DOMContentLoaded', hydrateWatch);

    document.addEventListener('click', function(event){
        const completeButton = event.target.closest('.mb-mark-video-complete');
        if (completeButton) {
            const section = completeButton.closest('.mb-gold-watch');
            if (section) {
                const isComplete = section.classList.contains('is-watch-complete');
                setWatchComplete(section, !isComplete);
            }
        }

        const transcriptToggle = event.target.closest('.mb-transcript-toggle');
        if (transcriptToggle) {
            const content = transcriptToggle.nextElementSibling;
            const open = transcriptToggle.getAttribute('aria-expanded') === 'true';
            transcriptToggle.setAttribute('aria-expanded', String(!open));
            if (content) content.hidden = open;
            const icon = transcriptToggle.querySelector('span:last-child');
            if (icon) icon.textContent = open ? '+' : '−';
        }

        const promptButton = event.target.closest('.mb-pause-reveal');
        if (promptButton) {
            const response = promptButton.nextElementSibling;
            const open = response && !response.hidden;
            if (response) response.hidden = open;
            promptButton.textContent = open ? 'Show Reflection Prompt' : 'Hide Reflection Prompt';
        }

        const chapter = event.target.closest('.mb-video-chapter');
        if (chapter) {
            const seconds = Number(chapter.dataset.videoTime || 0);
            const iframe = document.querySelector('.mb-featured-video iframe');
            if (iframe && iframe.src) {
                try {
                    const url = new URL(iframe.src);
                    url.searchParams.set('start', String(seconds));
                    url.searchParams.set('autoplay', '1');
                    iframe.src = url.toString();
                } catch (error) {}
            }
        }

        const printTranscript = event.target.closest('.mb-print-transcript');
        if (printTranscript) {
            const section = printTranscript.closest('.mb-transcript-section');
            const content = section ? section.querySelector('.mb-transcript-content') : null;
            if (content) {
                const popup = window.open('', '_blank', 'width=850,height=700');
                if (popup) {
                    popup.document.write(
                        '<!doctype html><html><head><title>MathBinder Video Transcript</title>' +
                        '<style>body{font-family:Arial,sans-serif;max-width:800px;margin:40px auto;line-height:1.65;color:#24323d}h1{color:#54269a}</style>' +
                        '</head><body><h1>Video Transcript</h1>' + content.querySelector('.mb-editor-content').innerHTML +
                        '<script>window.onload=function(){window.print();}</script></body></html>'
                    );
                    popup.document.close();
                }
            }
        }

        const practiceLink = event.target.closest('[data-go-practice]');
        if (practiceLink) {
            event.preventDefault();
            const target = document.querySelector('#practice');
            if (target) {
                target.scrollIntoView({behavior:'smooth', block:'start'});
                const toggle = target.querySelector('.mb-section-toggle');
                const content = target.querySelector('.mb-collapsible-content');
                if (toggle && content && content.hidden) toggle.click();
            }
        }
    });
})();


/* MathBinder 20.0 — Interactive Practice Studio */
(function(){
    function key(id){ return 'mathbinder_practice_' + id; }
    function read(id){ try{return JSON.parse(localStorage.getItem(key(id))||'{}')}catch(e){return{}} }
    function write(id,data){ try{localStorage.setItem(key(id),JSON.stringify(data))}catch(e){} }

    function normalize(v){ return String(v||'').toLowerCase().replace(/,/g,'').replace(/\s+/g,' ').trim(); }

    function update(section){
        const id = section.dataset.practicePost;
        const state = read(id);
        const stages = ['warmup','guided','independent','challenge'];
        let completed = 0;
        stages.forEach(function(stage){
            const done = !!state[stage];
            completed += done ? 1 : 0;
            const road = section.querySelector('[data-stage="'+stage+'"]');
            if (road) road.classList.toggle('is-complete',done);
        });
        const percent = Math.round(completed/stages.length*100);
        const p = section.querySelector('[data-practice-percent]');
        const c = section.querySelector('[data-practice-count]');
        if(p)p.textContent=percent+'%';
        if(c)c.textContent=completed+' of 4 stages complete';
        const finish=section.querySelector('.mb-practice-complete');
        if(finish)finish.hidden=completed<4;
    }

    function stageComplete(panel){
        const problems=Array.from(panel.querySelectorAll('.mb-practice-problem'));
        return problems.length && problems.every(function(p){return p.classList.contains('is-correct')});
    }

    document.addEventListener('DOMContentLoaded',function(){
        const section=document.querySelector('.mb-gold-practice');
        if(section)update(section);
    });

    document.addEventListener('click',function(event){
        const submit=event.target.closest('.mb-practice-submit');
        if(submit){
            const problem=submit.closest('.mb-practice-problem');
            const input=problem.querySelector('.mb-practice-answer');
            const feedback=problem.querySelector('.mb-practice-feedback');
            const correct=normalize(input.value)===normalize(problem.dataset.answer);
            problem.classList.toggle('is-correct',correct);
            problem.classList.toggle('is-incorrect',!correct);
            feedback.textContent=correct?'Correct! Explain why your answer makes sense.':'Not quite. Use a hint, check the place value, and try again.';
            feedback.className='mb-practice-feedback '+(correct?'is-correct':'is-incorrect');

            const panel=problem.closest('[data-stage-panel]');
            const section=problem.closest('.mb-gold-practice');
            if(stageComplete(panel)){
                const state=read(section.dataset.practicePost);
                state[panel.dataset.stagePanel]=true;
                write(section.dataset.practicePost,state);
                update(section);
            }
        }

        const hint=event.target.closest('.mb-practice-hint');
        if(hint){
            const feedback=hint.closest('.mb-practice-problem').querySelector('.mb-practice-feedback');
            feedback.textContent=hint.dataset.hint;
            feedback.className='mb-practice-feedback is-hint';
        }

        const solution=event.target.closest('.mb-practice-solution');
        if(solution){
            const feedback=solution.closest('.mb-practice-problem').querySelector('.mb-practice-feedback');
            feedback.textContent=solution.dataset.solution;
            feedback.className='mb-practice-feedback is-solution';
        }

        const challenge=event.target.closest('.mb-complete-challenge');
        if(challenge){
            const section=challenge.closest('.mb-gold-practice');
            const state=read(section.dataset.practicePost);
            state.challenge=true;
            write(section.dataset.practicePost,state);
            challenge.textContent='Challenge Completed ✓';
            update(section);
        }

        const journal=event.target.closest('[data-go-journal]');
        if(journal){
            event.preventDefault();
            const target=document.querySelector('#workbook');
            if(target){
                target.scrollIntoView({behavior:'smooth',block:'start'});
                const toggle=target.querySelector('.mb-section-toggle');
                const content=target.querySelector('.mb-collapsible-content');
                if(toggle&&content&&content.hidden)toggle.click();
            }
        }
    });

    document.addEventListener('input',function(event){
        const field=event.target.closest('.mb-practice-open-grid textarea');
        if(!field)return;
        const section=field.closest('.mb-gold-practice');
        const state=read(section.dataset.practicePost);
        state.responses=state.responses||{};
        state.responses[field.closest('article').innerText.slice(0,80)]=field.value;
        write(section.dataset.practicePost,state);
    });
})();


/* MathBinder 21.0 — Personal Binder Collection */
(function(){
    const LESSON_KEY = 'mathbinder_lesson_collection';
    const RESOURCE_KEY = 'mathbinder_resource_collection';

    function read(key, fallback){
        try {
            const value = JSON.parse(localStorage.getItem(key) || '');
            return value == null ? fallback : value;
        } catch (error) { return fallback; }
    }

    function write(key, value){
        try { localStorage.setItem(key, JSON.stringify(value)); } catch (error) {}
    }

    function lessons(){
        const value = read(LESSON_KEY, []);
        return Array.isArray(value) ? value : [];
    }

    function resources(){
        const value = read(RESOURCE_KEY, []);
        return Array.isArray(value) ? value : [];
    }

    function addCurrentLesson(section){
        const postId = String(section.dataset.resourcePost || '');
        const list = lessons();
        const existing = list.find(function(item){ return String(item.postId) === postId; });

        if (!existing) {
            list.unshift({
                postId: postId,
                title: section.dataset.resourceTitle || 'Binder Page',
                url: section.dataset.resourceUrl || '#',
                section: section.dataset.resourceSection || '',
                sectionTitle: section.dataset.resourceSectionTitle || 'Binder Section',
                added: new Date().toISOString()
            });
            write(LESSON_KEY, list);
        }
    }

    function render(section){
        if (!section) return;
        const list = lessons();
        const currentId = String(section.dataset.resourcePost || '');
        const saved = list.some(function(item){ return String(item.postId) === currentId; });
        const addButton = section.querySelector('.mb-add-lesson-to-binder');

        if (addButton) {
            addButton.classList.toggle('is-added', saved);
            addButton.textContent = saved ? '✓ Added to My Binder' : '＋ Add ' + (section.dataset.resourceTitle || 'Lesson');
        }

        const count = list.length;
        const countNode = section.querySelector('[data-binder-lesson-count]');
        const previewCount = section.querySelector('[data-preview-count]');
        const progress = section.querySelector('[data-binder-progress-fill]');
        const progressLabel = section.querySelector('[data-binder-progress-label]');
        const percent = Math.min(100, Math.round((count / 25) * 100));

        if (countNode) countNode.textContent = count;
        if (previewCount) previewCount.textContent = count + (count === 1 ? ' lesson' : ' lessons');
        if (progress) progress.style.width = percent + '%';
        if (progressLabel) progressLabel.textContent = count ? count + ' of 25 milestone lessons' : 'Start your collection';

        const currentResourceCount = resources().filter(function(item){
            return String(item.postId) === currentId;
        }).length;
        const resourceLabel = section.querySelector('[data-resource-count-label]');
        if (resourceLabel) {
            resourceLabel.textContent = currentResourceCount + (currentResourceCount === 1 ? ' resource collected' : ' resources collected');
        }

        const recent = section.querySelector('[data-recent-binder-list]');
        if (recent) {
            recent.innerHTML = '';
            if (!list.length) {
                recent.innerHTML = '<div class="mb-binder-empty"><strong>Your saved lessons will appear here.</strong><span>Build a binder you can use all year.</span></div>';
            } else {
                list.slice(0, 5).forEach(function(item){
                    const card = document.createElement('a');
                    card.href = item.url || '#';
                    card.className = 'mb-recent-binder-item';
                    card.innerHTML = '<span>✓</span><div><strong>' + escapeHtml(item.title) + '</strong><small>' + escapeHtml(item.sectionTitle || 'Binder Section') + '</small></div><em>Open →</em>';
                    recent.appendChild(card);
                });
            }
        }

        section.querySelectorAll('[data-milestone]').forEach(function(card){
            card.classList.toggle('is-earned', count >= Number(card.dataset.milestone || 0));
        });
    }

    function escapeHtml(value){
        const div = document.createElement('div');
        div.textContent = String(value || '');
        return div.innerHTML;
    }

    function printCollectedLessons(){
        const list = lessons();
        const popup = window.open('', '_blank', 'width=850,height=720');
        if (!popup) return;

        const grouped = {};
        list.forEach(function(item){
            const section = item.sectionTitle || 'Binder Section';
            grouped[section] = grouped[section] || [];
            grouped[section].push(item);
        });

        let body = '<h1>My MathBinder Review List</h1><p>Collected lessons saved on this device.</p>';
        if (!list.length) {
            body += '<p>No lessons have been collected yet.</p>';
        } else {
            Object.keys(grouped).forEach(function(section){
                body += '<h2>' + escapeHtml(section) + '</h2><ul>';
                grouped[section].forEach(function(item){
                    body += '<li>' + escapeHtml(item.title) + '</li>';
                });
                body += '</ul>';
            });
        }

        popup.document.write(
            '<!doctype html><html><head><title>My MathBinder Review List</title>' +
            '<style>body{font-family:Arial,sans-serif;max-width:780px;margin:40px auto;color:#24323d;line-height:1.55}h1,h2{color:#54269a}h2{margin-top:28px}li{margin:8px 0}</style>' +
            '</head><body>' + body + '<script>window.onload=function(){window.print();}</script></body></html>'
        );
        popup.document.close();
    }

    document.addEventListener('DOMContentLoaded', function(){
        render(document.querySelector('.mb-gold-binder'));
    });

    document.addEventListener('click', function(event){
        const add = event.target.closest('.mb-add-lesson-to-binder');
        if (add) {
            const section = add.closest('.mb-gold-binder');
            addCurrentLesson(section);
            render(section);
        }

        const favorite = event.target.closest('.mb-binder-favorite-button');
        if (favorite) {
            const headerFavorite = document.querySelector('.mb-favorite-page');
            if (headerFavorite) {
                headerFavorite.click();
                setTimeout(function(){
                    favorite.textContent = headerFavorite.getAttribute('aria-pressed') === 'true'
                        ? '★ Favorited'
                        : 'Add to Favorites';
                }, 0);
            }
        }

        const pack = event.target.closest('[data-study-pack="collected"]');
        if (pack) printCollectedLessons();

        const journal = event.target.closest('[data-binder-go-journal]');
        if (journal) {
            event.preventDefault();
            const target = document.querySelector('#workbook');
            if (target) {
                target.scrollIntoView({behavior:'smooth', block:'start'});
                const toggle = target.querySelector('.mb-section-toggle');
                const content = target.querySelector('.mb-collapsible-content');
                if (toggle && content && content.hidden) toggle.click();
            }
        }
    });

    document.addEventListener('click', function(event){
        const resourceAction = event.target.closest('[data-resource-action]');
        if (!resourceAction) return;
        const section = resourceAction.closest('.mb-gold-binder');
        if (section) setTimeout(function(){ render(section); }, 30);
    });
})();


/* MathBinder 22.0 — Journal History and Mastery Handoff */
(function(){
    function readJSON(key, fallback){
        try {
            const value = JSON.parse(localStorage.getItem(key) || '');
            return value == null ? fallback : value;
        } catch (error) { return fallback; }
    }

    function escapeHtml(value){
        const div = document.createElement('div');
        div.textContent = String(value || '');
        return div.innerHTML;
    }

    function journalEntries(){
        const entries = [];
        for (let i = 0; i < localStorage.length; i++) {
            const key = localStorage.key(i);
            if (!key || key.indexOf('mathbinder_workbook_') !== 0) continue;
            const data = readJSON(key, {});
            const postId = key.replace('mathbinder_workbook_', '');
            if (!data || !(data.notes || data.important || data.question || data.connection || data.confidence)) continue;
            entries.push({
                postId: postId,
                updated: data.updated || '',
                confidence: data.confidence || '',
                important: data.important || '',
                notes: data.notes || ''
            });
        }
        return entries.sort(function(a,b){
            return String(b.updated).localeCompare(String(a.updated));
        });
    }

    function renderHistory(){
        const journal = document.querySelector('.mb-workbook-section');
        if (!journal) return;

        const list = journal.querySelector('[data-journal-history]');
        const count = journal.querySelector('[data-journal-entry-count]');
        if (!list) return;

        const entries = journalEntries();
        if (count) count.textContent = entries.length + (entries.length === 1 ? ' entry' : ' entries');

        if (!entries.length) return;

        const currentTitle = journal.dataset.workbookTitle || 'Math Journal';
        list.innerHTML = '';

        entries.slice(0, 6).forEach(function(entry, index){
            const card = document.createElement('article');
            card.className = 'mb-journal-history-card';
            const label = index === 0 ? currentTitle : 'Saved Math Journal Entry';
            const date = entry.updated ? new Date(entry.updated).toLocaleDateString() : 'Saved locally';
            const confidenceLabels = {'1':'Still Learning','2':'Getting There','3':'Mostly Confident','4':'I Could Teach This'};
            card.innerHTML =
                '<div><span>' + escapeHtml(date) + '</span><h4>' + escapeHtml(label) + '</h4>' +
                '<p>' + escapeHtml(entry.important || entry.notes.slice(0,120) || 'Reflection saved on this device.') + '</p></div>' +
                '<strong>' + escapeHtml(confidenceLabels[entry.confidence] || 'Reflection Saved') + '</strong>';
            list.appendChild(card);
        });
    }

    document.addEventListener('DOMContentLoaded', function(){
        setTimeout(renderHistory, 50);
    });

    document.addEventListener('input', function(event){
        if (event.target.closest('.mb-workbook-section')) {
            clearTimeout(window._mbJournalHistoryTimer);
            window._mbJournalHistoryTimer = setTimeout(renderHistory, 900);
        }
    });

    document.addEventListener('click', function(event){
        const mastery = event.target.closest('[data-journal-go-mastery]');
        if (!mastery) return;
        event.preventDefault();

        const target = document.querySelector('#mastery');
        if (target) {
            target.scrollIntoView({behavior:'smooth', block:'start'});
            const toggle = target.querySelector('.mb-section-toggle');
            const content = target.querySelector('.mb-collapsible-content');
            if (toggle && content && content.hidden) toggle.click();
        }
    });
})();


/* MathBinder 23.0 — Gold Standard Mastery Check */
(function(){
    function key(id){ return 'mathbinder_mastery_' + id; }
    function badgeKey(){ return 'mathbinder_mastery_badges'; }

    function read(keyName, fallback){
        try {
            const value = JSON.parse(localStorage.getItem(keyName) || '');
            return value == null ? fallback : value;
        } catch (error) { return fallback; }
    }

    function write(keyName, value){
        try { localStorage.setItem(keyName, JSON.stringify(value)); } catch (error) {}
    }

    function items(section){
        return Array.from(section.querySelectorAll('.mb-mastery-item'));
    }

    function isAnswered(item){
        const selected = item.querySelector('.mb-mastery-choice.is-selected');
        const response = item.querySelector('.mb-mastery-response');
        return !!selected || !!(response && response.value.trim());
    }

    function updateProgress(section){
        const all = items(section);
        const answered = all.filter(isAnswered).length;
        const total = all.length || 1;
        const percent = Math.round(answered / total * 100);

        const label = section.querySelector('[data-mastery-progress-label]');
        const number = section.querySelector('[data-mastery-progress-percent]');
        const fill = section.querySelector('[data-mastery-progress-fill]');
        if (label) label.textContent = answered + ' of ' + all.length + ' answered';
        if (number) number.textContent = percent + '%';
        if (fill) fill.style.width = percent + '%';
    }

    function confidenceLabel(value){
        return {
            '1':'Need More Practice',
            '2':'Getting There',
            '3':'Confident',
            '4':'Could Teach This'
        }[String(value)] || 'Not selected';
    }

    function saveResult(section, result){
        const previous = read(key(section.dataset.masteryPost), {});
        result.attempts = Number(previous.attempts || 0) + 1;
        result.updated = new Date().toISOString();
        write(key(section.dataset.masteryPost), result);

        if (result.passed) {
            const badges = read(badgeKey(), []);
            const postId = String(section.dataset.masteryPost);
            if (!badges.some(function(item){ return String(item.postId) === postId; })) {
                badges.push({
                    postId: postId,
                    title: section.dataset.masteryTitle || 'MathBinder Master',
                    earned: result.updated
                });
                write(badgeKey(), badges);
            }
        }
        return result;
    }

    function score(section){
        const all = items(section);
        let correct = 0;
        let objective = 0;
        let constructedComplete = 0;

        all.forEach(function(item){
            const choice = item.querySelector('.mb-mastery-choice.is-selected');
            const response = item.querySelector('.mb-mastery-response');
            const status = item.querySelector('.mb-mastery-item-status');

            if (choice) {
                objective++;
                const right = choice.dataset.choice === choice.dataset.correct;
                item.classList.toggle('is-correct', right);
                item.classList.toggle('is-incorrect', !right);
                if (right) correct++;
                if (status) status.textContent = right ? 'Correct' : 'Review this skill';
            } else if (response && response.value.trim()) {
                constructedComplete++;
                item.classList.add('is-constructed-complete');
                if (status) status.textContent = 'Constructed response completed';
            }
        });

        const totalScored = objective || 1;
        const objectivePercent = Math.round(correct / totalScored * 100);
        const allComplete = all.every(isAnswered);
        const passed = allComplete && objectivePercent >= 80;

        return {
            correct: correct,
            objective: objective,
            constructedComplete: constructedComplete,
            total: all.length,
            percent: objectivePercent,
            passed: passed,
            confidence: section.querySelector('[data-mastery-confidence].is-selected')?.dataset.masteryConfidence || ''
        };
    }

    function showResults(section, result){
        const results = section.querySelector('.mb-mastery-results');
        if (!results) return;
        results.hidden = false;

        const title = results.querySelector('[data-mastery-result-title]');
        const message = results.querySelector('[data-mastery-result-message]');
        const scoreNode = results.querySelector('[data-mastery-score]');
        const correct = results.querySelector('[data-mastery-correct-count]');
        const confidence = results.querySelector('[data-mastery-confidence-result]');
        const attempt = results.querySelector('[data-mastery-attempt]');
        const status = results.querySelector('[data-mastery-status]');
        const guideTitle = results.querySelector('[data-mastery-guidance-title]');
        const guideText = results.querySelector('[data-mastery-guidance-text]');
        const badge = results.querySelector('.mb-mastery-badge');

        if (title) title.textContent = result.passed ? 'You Mastered It!' : 'Almost There';
        if (message) message.textContent = result.passed
            ? 'You demonstrated independent understanding of this lesson.'
            : 'Review the recommended sections, then try the Mastery Check again.';
        if (scoreNode) scoreNode.textContent = result.percent + '%';
        if (correct) correct.textContent = result.correct + ' of ' + result.objective + ' auto-scored';
        if (confidence) confidence.textContent = confidenceLabel(result.confidence);
        if (attempt) attempt.textContent = result.attempts;
        if (status) status.textContent = result.passed ? 'Mastered' : 'Keep Learning';
        if (guideTitle) guideTitle.textContent = result.passed ? 'Continue your learning' : 'Review Watch It and Practice It';
        if (guideText) guideText.textContent = result.passed
            ? 'Your mastery badge has been added to your learning record.'
            : 'Focus on place value language, worked examples, and guided practice before trying again.';
        if (badge) badge.hidden = !result.passed;

        results.classList.toggle('is-passed', result.passed);
        results.classList.toggle('is-review', !result.passed);
        results.scrollIntoView({behavior:'smooth', block:'start'});
    }

    function reset(section){
        section.querySelectorAll('.mb-mastery-choice').forEach(function(button){
            button.classList.remove('is-selected','is-correct','is-incorrect');
        });
        section.querySelectorAll('.mb-mastery-response').forEach(function(field){ field.value = ''; });
        section.querySelectorAll('.mb-mastery-item').forEach(function(item){
            item.classList.remove('is-correct','is-incorrect','is-constructed-complete');
            const status = item.querySelector('.mb-mastery-item-status');
            if (status) status.textContent = '';
        });
        section.querySelectorAll('.mb-constructed-guide').forEach(function(guide){ guide.hidden = true; });
        const results = section.querySelector('.mb-mastery-results');
        if (results) results.hidden = true;
        updateProgress(section);
        section.scrollIntoView({behavior:'smooth', block:'start'});
    }

    document.addEventListener('DOMContentLoaded', function(){
        const section = document.querySelector('.mb-gold-mastery');
        if (section) updateProgress(section);
    });

    document.addEventListener('click', function(event){
        const choice = event.target.closest('.mb-mastery-choice');
        if (choice) {
            const item = choice.closest('.mb-mastery-item');
            item.querySelectorAll('.mb-mastery-choice').forEach(function(button){
                button.classList.remove('is-selected');
            });
            choice.classList.add('is-selected');
            updateProgress(choice.closest('.mb-gold-mastery'));
        }

        const selfCheck = event.target.closest('.mb-self-check-response');
        if (selfCheck) {
            const item = selfCheck.closest('.mb-mastery-item');
            const response = item.querySelector('.mb-mastery-response');
            const guide = item.querySelector('.mb-constructed-guide');
            if (!response.value.trim()) {
                const status = item.querySelector('.mb-mastery-item-status');
                if (status) status.textContent = 'Write a response before reviewing it.';
            } else if (guide) {
                guide.hidden = false;
                selfCheck.textContent = 'Response Reviewed ✓';
                updateProgress(selfCheck.closest('.mb-gold-mastery'));
            }
        }

        const confidence = event.target.closest('[data-mastery-confidence]');
        if (confidence) {
            const section = confidence.closest('.mb-gold-mastery');
            section.querySelectorAll('[data-mastery-confidence]').forEach(function(button){
                button.classList.remove('is-selected');
            });
            confidence.classList.add('is-selected');
        }

        const submit = event.target.closest('.mb-submit-mastery-check');
        if (submit) {
            const section = submit.closest('.mb-gold-mastery');
            const all = items(section);
            if (!all.every(isAnswered)) {
                submit.textContent = 'Answer every question first';
                setTimeout(function(){ submit.textContent = 'Submit Mastery Check'; }, 1800);
                return;
            }

            let result = score(section);
            result = saveResult(section, result);
            showResults(section, result);
        }

        const retry = event.target.closest('.mb-retry-mastery');
        if (retry) reset(retry.closest('.mb-gold-mastery'));

        const review = event.target.closest('[data-review-section]');
        if (review) {
            event.preventDefault();
            const target = document.querySelector('#' + review.dataset.reviewSection);
            if (target) {
                target.scrollIntoView({behavior:'smooth', block:'start'});
                const toggle = target.querySelector('.mb-section-toggle');
                const content = target.querySelector('.mb-collapsible-content');
                if (toggle && content && content.hidden) toggle.click();
            }
        }
    });

    document.addEventListener('input', function(event){
        if (event.target.matches('.mb-mastery-response')) {
            updateProgress(event.target.closest('.mb-gold-mastery'));
        }
    });
})();


/* MathBinder 24.0 — Gold Standard Parent Help */
(function(){
    let parentTimer = null;
    let secondsRemaining = 300;

    function formatTime(seconds){
        const minutes = Math.floor(seconds / 60);
        const secondsPart = seconds % 60;
        return String(minutes).padStart(2,'0') + ':' + String(secondsPart).padStart(2,'0');
    }

    document.addEventListener('click', function(event){
        const timerButton = event.target.closest('.mb-start-parent-timer');
        if (timerButton) {
            const card = timerButton.closest('.mb-parent-review');
            const display = card ? card.querySelector('.mb-parent-timer') : null;
            if (!display) return;

            if (parentTimer) {
                clearInterval(parentTimer);
                parentTimer = null;
                secondsRemaining = 300;
                display.textContent = '05:00';
                timerButton.textContent = 'Start 5-Minute Timer';
                return;
            }

            timerButton.textContent = 'Stop Timer';
            parentTimer = setInterval(function(){
                secondsRemaining--;
                display.textContent = formatTime(secondsRemaining);
                if (secondsRemaining <= 0) {
                    clearInterval(parentTimer);
                    parentTimer = null;
                    secondsRemaining = 300;
                    display.textContent = 'Great review! ✓';
                    timerButton.textContent = 'Start Again';
                }
            }, 1000);
        }

        const printGuide = event.target.closest('.mb-print-parent-guide');
        if (printGuide) {
            const section = printGuide.closest('.mb-gold-parent');
            if (!section) return;

            const popup = window.open('', '_blank', 'width=900,height=800');
            if (!popup) return;

            const clone = section.cloneNode(true);
            clone.querySelectorAll('button, .mb-parent-next-step').forEach(function(node){ node.remove(); });
            popup.document.write(
                '<!doctype html><html><head><title>MathBinder Parent Guide</title>' +
                '<style>body{font-family:Arial,sans-serif;max-width:820px;margin:36px auto;color:#24323d;line-height:1.55}h1,h2,h3{color:#54269a}.mb-section-toggle{display:none}.mb-parent-help-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.mb-parent-card,.mb-parent-summary,.mb-parent-boundary{border:1px solid #cfdcdf;border-radius:14px;padding:16px;margin-bottom:14px}.mb-parent-card-heading{display:flex;gap:10px}.mb-parent-card-heading small,.mb-parent-kicker{color:#0f6f74;font-weight:bold;text-transform:uppercase;font-size:10px}.mb-parent-timer{display:none}@media print{body{margin:18px}}</style>' +
                '</head><body><h1>Parent Guide</h1>' + clone.querySelector('.mb-collapsible-content').innerHTML +
                '<script>window.onload=function(){window.print();}</script></body></html>'
            );
            popup.document.close();
        }

        const teacher = event.target.closest('[data-parent-go-teacher]');
        if (teacher) {
            event.preventDefault();
            const target = document.querySelector('#teacher-notes');
            if (target) {
                target.scrollIntoView({behavior:'smooth', block:'start'});
                const toggle = target.querySelector('.mb-section-toggle');
                const content = target.querySelector('.mb-collapsible-content');
                if (toggle && content && content.hidden) toggle.click();
            }
        }
    });
})();


/* MathBinder 25.1 — Teacher Notes Hotfix */
(function(){
    document.addEventListener('click', function(event){
        const printGuide = event.target.closest('.mb-print-teacher-guide');
        if (printGuide) {
            const section = printGuide.closest('.mb-gold-teacher');
            if (!section) return;
            const clone = section.cloneNode(true);
            clone.querySelectorAll('button, .mb-teacher-finish').forEach(function(node){ node.remove(); });
            const popup = window.open('', '_blank', 'width=960,height=850');
            if (!popup) return;
            const content = clone.querySelector('.mb-collapsible-content');
            popup.document.write('<!doctype html><html><head><title>MathBinder Teacher Guide</title><style>body{font-family:Arial,sans-serif;max-width:900px;margin:34px auto;color:#24323d;line-height:1.5}h1,h2,h3{color:#54269a}.mb-section-toggle{display:none}.mb-teacher-card,.mb-teacher-wide-card,.mb-teacher-standards,.mb-teacher-notes-callout{border:1px solid #cfdcdf;border-radius:14px;padding:16px;margin:14px 0}</style></head><body><h1>Teacher Guide</h1>' + (content ? content.innerHTML : '') + '<script>window.onload=function(){window.print();}</script></body></html>');
            popup.document.close();
        }
        const backTop = event.target.closest('[data-teacher-back-top]');
        if (backTop) {
            event.preventDefault();
            const target = document.querySelector('#lesson-top');
            if (target) target.scrollIntoView({behavior:'smooth', block:'start'});
        }
    });
})();
