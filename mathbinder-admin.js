
jQuery(function($){
    $(document).on('click','.mb-media-button',function(e){
        e.preventDefault();
        var target = $('#' + $(this).data('target'));
        var frame = wp.media({title:'Choose a MathBinder file',button:{text:'Use this file'},multiple:false});
        frame.on('select',function(){
            var attachment = frame.state().get('selection').first().toJSON();
            target.val(attachment.url).trigger('change');
        });
        frame.open();
    });
});
