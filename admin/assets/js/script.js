
document.addEventListener('DOMContentLoaded', function() {
    // Profile picture preview
    const profilePicInput = document.getElementById('user_profile_picture');
    if (profilePicInput) {
        profilePicInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.querySelector('.profile-img').src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
    }
    
    // Password confirmation validation
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    
    if (newPassword && confirmPassword) {
        function validatePassword() {
            if (newPassword.value != confirmPassword.value) {
                confirmPassword.setCustomValidity("Passwords don't match");
            } else {
                confirmPassword.setCustomValidity('');
            }
        }
        
        newPassword.addEventListener('change', validatePassword);
        confirmPassword.addEventListener('keyup', validatePassword);
    }
    
    // Handle delete account confirmation
    const deleteBtn = document.getElementById('confirmDelete');
    if (deleteBtn) {
        deleteBtn.addEventListener('click', function() {
            const deleteConfirmation = document.getElementById('delete_confirmation');
            const deletePassword = document.getElementById('delete_password');
            
            if (deleteConfirmation.value === 'DELETE' && deletePassword.value.length > 0) {
                document.getElementById('delete-account-form').submit();
            } else {
                alert('Please type "DELETE" and enter your password to confirm account deletion.');
            }
        });
    }
    
    // Auto-dismiss alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.5s';
            setTimeout(function() {
                alert.style.display = 'none';
            }, 500);
        }, 5000);
    });
});