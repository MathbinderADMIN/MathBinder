(function () {
    'use strict';
    function ready(fn) { if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn); else fn(); }
    ready(function () {
        var form = document.querySelector('[data-mb-mastery-builder]'); if (!form) return;
        var step = 1, previewed = false, generated = false;
        var steps = Array.prototype.slice.call(form.querySelectorAll('[data-mb-step]'));
        var dots = Array.prototype.slice.call(form.querySelectorAll('[data-mb-step-dot]'));
        var prev = form.querySelector('[data-mb-prev]'), next = form.querySelector('[data-mb-next]'), publish = form.querySelector('[data-mb-publish]');
        var orderField = form.querySelector('[data-mb-lesson-order]'), selectedList = form.querySelector('[data-mb-selected-lessons]');
        var lessonBoxes = Array.prototype.slice.call(form.querySelectorAll('[data-mb-lesson]'));
        var selectedIds = (orderField.value || '').split(',').filter(Boolean);
        function field(name) { return form.elements[name]; }
        function value(name) { return field(name) ? String(field(name).value || '').trim() : ''; }
        function set(name, text) { if (field(name)) field(name).value = text || ''; }
        function showStep(number) {
            step = Math.max(1, Math.min(3, number));
            steps.forEach(function (panel) { var active = Number(panel.getAttribute('data-mb-step')) === step; panel.hidden = !active; panel.classList.toggle('is-active', active); });
            dots.forEach(function (dot) { var n = Number(dot.getAttribute('data-mb-step-dot')); dot.classList.toggle('is-active', n === step); dot.classList.toggle('is-complete', n < step); });
            prev.disabled = step === 1; next.hidden = step === 3; publish.hidden = step !== 3;
            steps[step - 1].scrollIntoView({behavior:'smooth', block:'nearest'});
        }
        function validateStep() {
            var required = Array.prototype.slice.call(steps[step - 1].querySelectorAll('[required]'));
            for (var i=0;i<required.length;i++) if (!required[i].checkValidity()) { required[i].reportValidity(); return false; }
            if (step === 1 && !generated) { window.alert('Generate and verify the mastery path first.'); return false; }
            if (step === 2 && selectedIds.length === 0) { window.alert('Select at least one MathBinder lesson before continuing.'); return false; }
            return true;
        }
        function titleFor(id) { var box=lessonBoxes.find(function(item){return item.value===String(id);}); return box ? box.getAttribute('data-title') : 'Lesson'; }
        function renderSequence() {
            selectedIds=selectedIds.filter(function(id,index,all){return all.indexOf(id)===index&&lessonBoxes.some(function(box){return box.value===id&&box.checked;});});
            lessonBoxes.forEach(function(box){if(box.checked&&selectedIds.indexOf(box.value)===-1)selectedIds.push(box.value);});
            orderField.value=selectedIds.join(','); selectedList.innerHTML='';
            selectedIds.forEach(function(id,index){var item=document.createElement('li'),label=document.createElement('span');label.textContent=(index+1)+'. '+titleFor(id);item.appendChild(label);selectedList.appendChild(item);});
        }
        function suggestLessons(title, skills) {
            var words=(title+' '+skills.join(' ')).toLowerCase().split(/[^a-z0-9]+/).filter(function(w){return w.length>3;}), scored=[];
            lessonBoxes.forEach(function(box){var name=String(box.getAttribute('data-title')||'').toLowerCase(),score=words.reduce(function(total,w){return total+(name.indexOf(w)>-1?1:0);},0);if(score)scored.push({box:box,score:score});box.checked=false;});
            scored.sort(function(a,b){return b.score-a.score;}); (scored.length?scored.slice(0,3):lessonBoxes.slice(0,1)).forEach(function(item){(item.box||item).checked=true;}); selectedIds=[]; renderSequence();
        }
        function numbered(items) { return items.map(function(q,i){return (i+1)+'. '+q;}).join('\n\n'); }
        function applyDraft(draft) {
            var title=value('path_title'), standard=draft.standard_code+(draft.standard_text?' — '+draft.standard_text:'');
            set('target_standard', standard.substring(0,1000));
            set('objectives', draft.objectives+'\n\nVerified skills:\n- '+draft.skills.join('\n- ')); set('prerequisites',draft.prerequisites);
            set('pretest_title',title+' Diagnostic Pretest'); set('pretest_instructions','Directions: Complete all 8 questions independently. Show your reasoning and representations when requested.\n\n'+numbered(draft.pretest_questions));
            set('foundational',draft.foundational); set('developing',draft.developing); set('near_mastery',draft.near_mastery); set('extension',draft.extension);
            set('evidence_requirements',draft.evidence); set('posttest_title',title+' Mastery Posttest');
            set('posttest_instructions','Directions: Complete all 8 questions independently. Show your reasoning and representations when requested. A score of 80% demonstrates mastery.\n\n'+numbered(draft.posttest_questions));
            set('reassessment',draft.reassessment); set('extension_activity',draft.extension_activity); suggestLessons(title,draft.skills);
        }
        function generate() {
            ['mastery_grade_level','path_title','target_standard'].forEach(function(name){if(field(name))field(name).setCustomValidity('');});
            if (!field('mastery_grade_level').checkValidity() || !field('path_title').checkValidity() || !field('target_standard').checkValidity()) { form.reportValidity(); return; }
            var config=window.MathBinderMasteryAI||{}, button=form.querySelector('[data-mb-generate]'), status=form.querySelector('[data-mb-generation-status]');
            if(!config.ajaxUrl||!config.nonce){status.textContent='The secure generator is unavailable. Ask the site administrator to check MathBinder configuration.';status.classList.remove('is-ready');return;}
            button.disabled=true; status.textContent='Verifying the California standard and generating aligned materials…'; status.classList.remove('is-ready');
            var data=new FormData(); data.append('action','mb_generate_mastery_path'); data.append('nonce',config.nonce); data.append('grade',value('mastery_grade_level')); data.append('title',value('path_title')); data.append('standard',value('target_standard'));
            fetch(config.ajaxUrl,{method:'POST',credentials:'same-origin',body:data}).then(function(response){return response.json().then(function(body){if(!response.ok||!body.success)throw new Error(body&&body.data&&body.data.message?body.data.message:'Generation failed.');return body;});}).then(function(body){
                applyDraft(body.data.draft); generated=true; previewed=false; publish.disabled=true; button.disabled=false;
                status.textContent='California standard verified. The aligned draft is ready for teacher review. '+body.data.draft.alignment_check; status.classList.add('is-ready'); showStep(2);
            }).catch(function(error){generated=false;button.disabled=false;status.textContent=error.message;status.classList.remove('is-ready');});
        }
        var generateButton=form.querySelector('[data-mb-generate]'); if(generateButton)generateButton.addEventListener('click',generate);
        lessonBoxes.forEach(function(box){box.addEventListener('change',function(){previewed=false;publish.disabled=true;renderSequence();});});
        prev.addEventListener('click',function(){showStep(step-1);}); next.addEventListener('click',function(){if(validateStep())showStep(step+1);});
        var dialog=form.querySelector('[data-mb-preview-dialog]'), previewButton=form.querySelector('[data-mb-preview]'), previewStatus=form.querySelector('[data-mb-preview-status]');
        function addPreview(list,heading,detail){var item=document.createElement('li'),strong=document.createElement('strong'),span=document.createElement('span');strong.textContent=heading;span.textContent=detail||'Not provided';item.appendChild(strong);item.appendChild(span);list.appendChild(item);}
        previewButton.addEventListener('click',function(){if(!validateStep())return;form.querySelector('[data-preview-title]').textContent=value('path_title');var list=form.querySelector('[data-mb-preview-sequence]');list.innerHTML='';addPreview(list,'Grade',value('mastery_grade_level'));addPreview(list,'Target standard',value('target_standard'));addPreview(list,'1. Diagnostic pretest',value('pretest_instructions'));addPreview(list,'2. Foundational assignment',value('foundational'));addPreview(list,'3. Developing assignment',value('developing'));addPreview(list,'4. Near-mastery assignment',value('near_mastery'));selectedIds.forEach(function(id){addPreview(list,'Suggested MathBinder lesson',titleFor(id));});addPreview(list,'5. Evidence Folder',value('evidence_requirements'));addPreview(list,'6. Mastery posttest',value('posttest_instructions'));addPreview(list,'Below 80%',value('reassessment'));addPreview(list,'At or above 80%',value('extension_activity'));previewed=true;publish.disabled=false;previewStatus.textContent='Complete preview reviewed. This path is ready for teacher approval.';previewStatus.classList.add('is-ready');if(typeof dialog.showModal==='function')dialog.showModal();else dialog.setAttribute('open','open');});
        Array.prototype.slice.call(form.querySelectorAll('[data-mb-close-preview]')).forEach(function(button){button.addEventListener('click',function(){if(typeof dialog.close==='function')dialog.close();else dialog.removeAttribute('open');});});
        form.addEventListener('input',function(event){if(event.target.name!=='save_mode'){previewed=false;publish.disabled=true;if(previewStatus){previewStatus.textContent='Changes made. Open the complete preview again before publishing.';previewStatus.classList.remove('is-ready');}}});
        form.addEventListener('submit',function(event){if(event.submitter&&event.submitter.value==='published'&&!previewed){event.preventDefault();window.alert('Open the complete preview before approving and publishing this mastery path.');}});
        if(value('objectives'))generated=true; renderSequence(); showStep(1);
    });
}());
