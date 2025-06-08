(function($){
    $(function(){
        // Confirmation modal on 'Publish'
        if( $('#publish').length ){
            $('#publish').on('click', function(e){
                if( ! confirm('Are you sure you want to start this email campaign?') ){
                    e.preventDefault();
                }
            });
        }
    });
})(jQuery);
