(function(){
    'use strict';
    function ready(fn){ if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',fn); else fn(); }
    ready(function(){
        var isDashboard=document.body.classList.contains('page-slug-teacher-dashboard') || /\/teacher-dashboard\/?$/.test(window.location.pathname);
        if(isDashboard && !window.location.hash){
            if('scrollRestoration' in window.history) window.history.scrollRestoration='manual';
            window.requestAnimationFrame(function(){window.scrollTo(0,0);});
        }
        Array.prototype.slice.call(document.querySelectorAll('[data-mb-copy-link]')).forEach(function(button){
            button.addEventListener('click',function(){
                var link=button.getAttribute('data-mb-copy-link')||'';
                if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(link).then(function(){button.textContent='Copied!';window.setTimeout(function(){button.textContent='Copy Link';},1600);});}
            });
        });
        var body=document.querySelector('[data-mb-roster]'); if(!body) return;
        var search=document.querySelector('[data-mb-roster-search]');
        var classFilter=document.querySelector('[data-mb-roster-class]');
        var status=document.querySelector('[data-mb-roster-status]');
        var empty=document.querySelector('[data-mb-roster-empty]');
        var rows=Array.prototype.slice.call(body.querySelectorAll('tr'));
        function filter(){
            var term=(search.value||'').trim().toLowerCase(), classId=classFilter.value, state=status.value, shown=0;
            rows.forEach(function(row){
                var match=(!term||row.dataset.name.indexOf(term)!==-1)&&(!classId||row.dataset.class===classId)&&(!state||(state==='past-due'?row.dataset.pastDue==='1':row.dataset.activity===state));
                row.hidden=!match; if(match) shown++;
            });
            empty.hidden=shown!==0;
        }
        search.addEventListener('input',filter); classFilter.addEventListener('change',filter); status.addEventListener('change',filter);
    });
}());
document.addEventListener('DOMContentLoaded',function(){document.querySelectorAll('[data-mb-roster] tr').forEach(function(row){var name=row.querySelector('td strong');var progress=row.querySelector('.mb-roster-actions a');if(name&&progress&&!name.querySelector('a')){var link=document.createElement('a');link.href=progress.href;link.textContent=name.textContent;name.textContent='';name.appendChild(link);}});});
