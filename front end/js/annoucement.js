$(document).ready(function() {
    // Handle comment submission
    $('#comment-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = $(this).serialize();
        
        $.ajax({
            type: 'POST',
            url: 'comments.php',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Reload comments
                    location.reload();
                } else {
                    alert(response.message);
                }
            },
            error: function() {
                alert('An error occurred while posting your comment.');
            }
        });
    });
    
    // Handle reply links
    $(document).on('click', '.reply-link', function(e) {
        e.preventDefault();
        const commentId = $(this).data('comment-id');
        $('#parent_id').val(commentId);
        $('.reply-info').show();
        $('html, body').animate({
            scrollTop: $('#comment-form').offset().top - 100
        }, 500);
        $('#comment_content').focus();
    });
    
    // Cancel reply
    $('.cancel-reply').click(function(e) {
        e.preventDefault();
        $('#parent_id').val('');
        $('.reply-info').hide();
    });
    
    <?php if ($authError): ?>
    alert('Authentication error: <?php echo htmlspecialchars($authError); ?>');
    <?php endif; ?>
    
    <?php if ($authSuccess): ?>
    alert('Successfully signed in!');
    <?php endif; ?>
});