(function(){
    'use strict';
    function ready(fn){ if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',fn); else fn(); }
    ready(function(){
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
