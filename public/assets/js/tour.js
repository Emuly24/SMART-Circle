/*
 * Guided tours: a few steps that point at the main parts of a page, chosen by who is signed in
 * (data-role on <body>) and the page (data-path). Shown once automatically on the first visit,
 * and any time from the "Tour" button. Steps whose element is not on the page are skipped.
 */
(function () {
    var T = {
        'guest:/': [
            ['.hero', 'Welcome to SMART Circle', 'Free help for Form 1–4 students in Mathematics, Physics, Chemistry, Biology and English grammar, with lessons you can replay any time.'],
            ['.hero__actions', 'Apply in 15 minutes', 'Applying is free. Your parent or guardian does not need to be with you; we contact them after admission.'],
            ['.seats', 'Seats', 'Places are kept for girls and boys in every form. This shows how many are still open.'],
            ['.hero__links', 'Already applied?', 'Check your application, or set up your account if you have been admitted.'],
            ['.theme-menu', 'Easy on the eyes', 'Choose Light, Eye comfort or Dark, whichever is comfortable for you.'],
        ],
        'student:/dashboard': [
            ['.term-strip', 'Where we are', 'The school term and week, and how many days to MANEB if you are in Form 2 or 4.'],
            ['#private-room', 'Your private room', 'Something affecting your studies? Talk to the tutor privately. Only you and the tutor can read it.'],
            ['a.tile[href="/lessons"]', 'Lessons', 'Replay recorded lessons as many times as you need. They open when your school has taught the topic.'],
            ['a.tile[href="/topics"]', 'Ask for help', 'Stuck on a topic your school has taught? Tell the tutor where you get lost and ask for a lesson.'],
            ['a.tile[href="/notes"]', 'Notes and slides', 'Read at your own pace; each short exercise opens the next part.'],
            ['a.tile[href="/assessments"]', 'Exams and assignments', 'Marks come with what went well, where it went off track and your next step.'],
            ['a.tile[href="/office-hours"]', 'Office hours', 'Book a call with the tutor when you are stuck.'],
            ['.nav-avatar', 'Your profile', 'Add a profile picture here.'],
        ],
        'partner:/dashboard': [
            ['.term-strip', 'The school year', 'Terms and weeks from the national calendar.'],
            ['.school-overview, .progress-table', 'Your school', 'What your school has taught, so SMART Circle lessons follow your teaching.'],
            ['a[href="/partner/coverage"]', 'Topics taught', 'Tick topics as your classes cover them. This is what opens lessons to your students.'],
            ['a.community-card[href="/partner/contribute"]', 'Contribute', 'Confirm your calendar, share your syllabus or scheme of work, and suggest missing topics.'],
        ],
        'tutor:/dashboard': [
            ['#private-room', 'Private rooms', 'Kept, private conversations with students. Invite a student from their page.'],
            ['a.tile[href="/studio/practice"]', 'Try as', 'See and test every page as a student, teacher, alumnus or team tutor, with practice accounts.'],
            ['a.tile[href="/studio/students"]', 'Students', 'Activity, gentle check-in flags, and one-tap call, WhatsApp or SMS.'],
            ['a.tile[href="/studio/marking"]', 'Marking', 'Everything waiting for your encouraging marks, and re-check requests.'],
            ['a.tile[href="/studio/guardians"]', 'Guardians', 'Welcome messages in Chichewa or English, confirmations and the monthly update.'],
            ['a.tile[href="/studio/team"]', 'The Team', 'Invite volunteer tutors for a subject.'],
            ['a.tile[href="/studio/settings"]', 'Settings and backup', 'Your WhatsApp number, the community link, and a database backup.'],
        ],
        'team:/dashboard': [
            ['.lead', 'Your subjects', 'Everything you see is limited to the subjects you teach.'],
            ['a.tile[href="/studio/lab"]', 'Recording studio', 'Teach on the board; then upload the lesson from the Replay tab.'],
            ['a.tile[href="/studio/marking"]', 'Marking', 'Mark with “Got it”, “Partly there” or “Not yet”, always with a next step.'],
            ['a.tile[href="/studio/requests"]', 'Topic requests', 'What students in your subjects are asking for.'],
            ['a.tile[href="/messages"]', 'Messages', 'Write to students and the team, or announce to students of your subjects.'],
        ],
        'alumni:/dashboard': [
            ['a.tile[href="/alumni"]', 'What you are doing now', 'Tell us, and ask for help with selection, loans or applications.'],
            ['a.tile[href="/alumni/community"]', 'Community', 'Questions, opportunities and stories from SMART Circle alumni.'],
            ['a.tile[href="/office-hours"]', 'Office hours', 'Book a call with the tutor.'],
        ],
    };
    var body = document.body;
    var key = (body.getAttribute('data-role') || 'guest') + ':' + (body.getAttribute('data-path') || '/');
    var steps = (T[key] || []).filter(function (s) { return document.querySelector(s[0]); });
    var button = document.querySelector('.tour-start');
    if (steps.length === 0 || !button) { return; }
    button.hidden = false;

    var seenKey = 'sc-tour-' + key;
    var store = {
        get: function () { try { return localStorage.getItem(seenKey); } catch (e) { return '1'; } },
        set: function () { try { localStorage.setItem(seenKey, '1'); } catch (e) { /* private mode */ } },
    };
    var i = 0, panel = null, target = null;

    function close() {
        if (target) { target.classList.remove('tour-target'); }
        if (panel) { panel.remove(); panel = null; }
        document.removeEventListener('keydown', onKey);
        store.set();
        button.focus({ preventScroll: true });
    }
    function onKey(e) {
        if (e.key === 'Escape') { close(); }
        if (e.key === 'ArrowRight') { go(i + 1); }
        if (e.key === 'ArrowLeft') { go(i - 1); }
    }
    function el(tag, cls, text) {
        var n = document.createElement(tag);
        if (cls) { n.className = cls; }
        if (text) { n.textContent = text; }
        return n;
    }
    function go(n) {
        if (n < 0) { return; }
        if (n >= steps.length) { close(); return; }
        i = n;
        if (target) { target.classList.remove('tour-target'); }
        target = document.querySelector(steps[i][0]);
        target.classList.add('tour-target');
        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        target.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'center' });
        if (!panel) {
            panel = el('div', 'tour-panel');
            panel.setAttribute('role', 'dialog');
            panel.setAttribute('aria-live', 'polite');
            panel.setAttribute('aria-label', 'Guided tour');
            document.body.appendChild(panel);
            document.addEventListener('keydown', onKey);
        }
        panel.textContent = '';
        panel.appendChild(el('p', 'tour-panel__count', 'Step ' + (i + 1) + ' of ' + steps.length));
        panel.appendChild(el('h2', 'tour-panel__title', steps[i][1]));
        panel.appendChild(el('p', 'tour-panel__text', steps[i][2]));
        var row = el('div', 'tour-panel__actions');
        var skip = el('button', 'btn btn--small btn--ghost', 'Close');
        skip.type = 'button';
        skip.addEventListener('click', close);
        row.appendChild(skip);
        if (i > 0) {
            var back = el('button', 'btn btn--small btn--ghost', 'Back');
            back.type = 'button';
            back.addEventListener('click', function () { go(i - 1); });
            row.appendChild(back);
        }
        var next = el('button', 'btn btn--small', i === steps.length - 1 ? 'Done' : 'Next');
        next.type = 'button';
        next.addEventListener('click', function () { go(i + 1); });
        row.appendChild(next);
        panel.appendChild(row);
        next.focus({ preventScroll: true });
    }

    button.addEventListener('click', function () { go(0); });
    if (!store.get()) { window.setTimeout(function () { go(0); }, 600); }
})();
