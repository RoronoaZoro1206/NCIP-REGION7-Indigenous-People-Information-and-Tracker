// Fix both view count tracking and profile image issues
document.addEventListener('DOMContentLoaded', function() {
    console.log('Document ready - initializing comment system');
    
    // Initial setup - hide extra comments
    setupCommentVisibility();

    // Handle comment submission
    document.querySelectorAll('.comment-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            console.log('Comment form submitted');
            
            const formData = new FormData(this);
            const announcementId = this.dataset.announcementId;
            const commentsContainer = document.querySelector(`#comments-container-${announcementId}`);
            
            fetch('comments.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(response => {
                if (response.success) {
                    console.log('Comment posted successfully, updating comments...');
                    this.querySelector('textarea').value = '';
                    this.querySelector('.parent-id-input').value = '';
                    
                    // Reset form to comment mode if it was in reply mode
                    if (this.classList.contains('replying-mode')) {
                        document.querySelectorAll('.comment-item').forEach(item => {
                            item.classList.remove('being-replied-to');
                        });
                        this.querySelector('.reply-info').style.display = 'none';
                        this.querySelector('textarea').placeholder = 'Write your comment here...';
                        this.querySelector('button[type="submit"]').textContent = 'Post Comment';
                        this.classList.remove('replying-mode');
                    }
                    
                    // Reload comments for this announcement
                    fetch(`get_comments.php?ann_id=${announcementId}`)
                        .then(res => res.text())
                        .then(html => {
                            commentsContainer.innerHTML = html;
                            setupCommentVisibility();
                            
                            fixProfileImages();
                        });
                    
                    // Also refresh the recent comments sidebar
                    autoRefreshRecentComments();
                    
                } else {
                    alert(response.message || 'An error occurred while posting your comment.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        });
    });
    
    // Handle reply link clicks
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('reply-link')) {
            e.preventDefault();
            const commentId = e.target.dataset.commentId;
            const announcementId = e.target.dataset.announcementId;
            const commentAuthor = e.target.closest('.comment-item').querySelector('.author strong').textContent;
            
            // Remove existing reply status
            document.querySelectorAll('.comment-item').forEach(item => {
                item.classList.remove('being-replied-to');
            });
            e.target.closest('.comment-item').classList.add('being-replied-to');

            const form = document.querySelector(`.comment-form[data-announcement-id="${announcementId}"]`);
            form.querySelector('.comment-mode-text').textContent = 'Replying';
            form.querySelector('.parent-id-input').value = commentId;
            form.querySelector('.reply-info span').textContent = `Replying to ${commentAuthor}`;
            form.querySelector('.reply-info').style.display = 'block';
            form.querySelector('textarea').placeholder = `Write your reply to ${commentAuthor} here...`;
            form.querySelector('button[type="submit"]').textContent = 'Post Reply';
            form.classList.add('replying-mode');

            // Scroll to form
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            form.querySelector('textarea').focus();
        }
    });

    // Handle cancel reply
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('cancel-reply')) {
            e.preventDefault();
            document.querySelectorAll('.comment-item').forEach(item => {
                item.classList.remove('being-replied-to');
            });
            const form = e.target.closest('form');
            form.querySelector('.comment-mode-text').textContent = 'Commenting';
            form.querySelector('.parent-id-input').value = '';
            form.querySelector('.reply-info').style.display = 'none';
            form.querySelector('textarea').placeholder = 'Write your comment here...';
            form.querySelector('button[type="submit"]').textContent = 'Post Comment';
            form.classList.remove('replying-mode');
        }
    });
    
    // Add global click handler for show more/less buttons
    document.addEventListener('click', function(e) {
        // Handle "Show more" button clicks
        if (e.target.classList.contains('show-more-comments')) {
            console.log('Show more clicked');
            const container = e.target.parentElement;
            const isRecent = container.id === 'recent-comments-container';
            const comments = container.querySelectorAll(isRecent ? '.recent-comment-item.hidden' : '.comment-item.hidden');
            
            // Show all hidden comments
            comments.forEach(comment => {
                comment.classList.remove('hidden');
                comment.classList.add('visible');
            });
            
            // Change button text and class
            e.target.textContent = 'Show less';
            e.target.classList.remove('show-more-comments');
            e.target.classList.add('show-less-comments');
        }
        
        // Handle "Show less" button clicks
        else if (e.target.classList.contains('show-less-comments')) {
            console.log('Show less clicked');
            const container = e.target.parentElement;
            const isRecent = container.id === 'recent-comments-container';
            const comments = container.querySelectorAll(isRecent ? '.recent-comment-item' : '.comment-item');
            const commentCount = comments.length;
            
            comments.forEach((comment, index) => {
                if (index >= 2) {
                    comment.classList.add('hidden');
                    comment.classList.remove('visible');
                }
            });
            
            // Change button text and class
            e.target.textContent = `Show more (${commentCount - 2} more)`;
            e.target.classList.remove('show-less-comments');
            e.target.classList.add('show-more-comments');
        }
    });
    
    // Track clicks on all "Read more" links
    document.querySelectorAll('.not-btn.more-link').forEach(link => {
        // Add data-announcement-id attributes if missing
        if (!link.hasAttribute('data-announcement-id')) {
            let url = link.getAttribute('href');
            let announcementId = 0;
            
            if (url.includes('advisory')) {
                announcementId = 1;
                link.setAttribute('data-announcement-id', '1');
            } else if (url.includes('makasaysayang-araw-para-sa-mga-eskaya')) {
                announcementId = 2;
                link.setAttribute('data-announcement-id', '2');
            } else if (url.includes('ncip-nakiisa-sa-forum-para-sa-kababaihan-mula-sa-ibat-ibang-sektor')) {
                announcementId = 3;
                link.setAttribute('data-announcement-id', '3');
            }
        }
        
        link.addEventListener('click', function(e) {
            const announcementId = this.getAttribute('data-announcement-id');
            
            if (announcementId) {
                // Send tracking data
                fetch('track_clicks.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'ann_id=' + announcementId
                })
                .then(response => response.json())
                .then(data => {
                    console.log('Click tracked:', data);
                    
                    // Update the view count in the UI without refreshing
                    updateViewCountUI(announcementId);
                    
                    // Update popular readings section
                    updatePopularReadings();
                })
                .catch(error => {
                    console.error('Error tracking click:', error);
                });
            }
        });
    });
    
    // Function to update view count in UI
    function updateViewCountUI(announcementId) {
        // Fetch the updated count from the server
        fetch('get_view_count.php?ann_id=' + announcementId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Find and update all instances of this announcement's view count
                    const viewElements = document.querySelectorAll(`.view-count[data-announcement-id="${announcementId}"]`);
                    viewElements.forEach(element => {
                        element.innerHTML = `<small>(${data.count} ${data.count <= 1 ? 'view' : 'views'})</small>`;
                    });
                    
                    // Also check in the popular readings sidebar
                    const popularReadings = document.querySelectorAll('.recent-post .hentry-post');
                    popularReadings.forEach(reading => {
                        const link = reading.querySelector('a.title');
                        if (link) {
                            let url = link.getAttribute('href');
                            let readingId = 0;
                            
                            if (url.includes('advisory')) {
                                readingId = 1;
                            } else if (url.includes('makasaysayang-araw-para-sa-mga-eskaya')) {
                                readingId = 2;
                            } else if (url.includes('ncip-nakiisa-sa-forum-para-sa-kababaihan-mula-sa-ibat-ibang-sektor')) {
                                readingId = 3;
                            }
                            
                            if (readingId == announcementId) {
                                const viewCount = reading.querySelector('.view-count');
                                if (viewCount) {
                                    viewCount.innerHTML = `<small>(${data.count} ${data.count <= 1 ? 'view' : 'views'})</small>`;
                                }
                            }
                        }
                    });
                }
            })
            .catch(error => {
                console.error('Error fetching updated view count:', error);
            });
    }
    
    // Function to update popular readings section
    function updatePopularReadings() {
        fetch('get_popular_readings.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const popularReadingsContainer = document.querySelector('.recent-post.group');
                    if (popularReadingsContainer) {
                        popularReadingsContainer.innerHTML = data.html;
                        console.log('Popular readings updated!');
                        
                       
                        fixProfileImages();
                        fixBrokenImages();
                        
                        // Re-add data attributes to the new elements
                        document.querySelectorAll('.recent-post .hentry-post').forEach(post => {
                            const link = post.querySelector('a.title');
                            const viewCount = post.querySelector('.view-count');
                            
                            if (link && viewCount && !viewCount.hasAttribute('data-announcement-id')) {
                                let url = link.getAttribute('href');
                                let announcementId = 0;
                                
                                if (url.includes('advisory')) {
                                    announcementId = 1;
                                } else if (url.includes('makasaysayang-araw-para-sa-mga-eskaya')) {
                                    announcementId = 2;
                                } else if (url.includes('ncip-nakiisa-sa-forum-para-sa-kababaihan-mula-sa-ibat-ibang-sektor')) {
                                    announcementId = 3;
                                }
                                
                                if (announcementId > 0) {
                                    viewCount.setAttribute('data-announcement-id', announcementId);
                                }
                            }
                        });
                    }
                }
            })
            .catch(error => {
                console.error('Error updating popular readings:', error);
            });
    }
    
    // Fix profile image loading issues
    function fixProfileImages() {
        document.querySelectorAll('img.avatar, img.photo').forEach(img => {
            // Add error handler to all avatar images
            img.onerror = function() {
                // Replace with default avatar if loading fails
                console.log('Image failed to load, replacing with default:', this.src);
                this.src = 'images/gavatar.png';
                this.onerror = null; // Prevent infinite loop
            };
            
            // If image is already broken, fix it now
            if (img.complete && (img.naturalWidth === 0 || img.naturalHeight === 0)) {
                console.log('Fixing already broken image:', img.src);
                img.src = 'images/gavatar.png';
            }
        });
    }
    
    // Fix profile images on page load
    fixProfileImages();
    
    // Add data attributes to view count elements if missing
    document.querySelectorAll('.recent-post .hentry-post').forEach(post => {
        const link = post.querySelector('a.title');
        const viewCount = post.querySelector('.view-count');
        
        if (link && viewCount && !viewCount.hasAttribute('data-announcement-id')) {
            let url = link.getAttribute('href');
            let announcementId = 0;
            
            if (url.includes('advisory')) {
                announcementId = 1;
            } else if (url.includes('makasaysayang-araw-para-sa-mga-eskaya')) {
                announcementId = 2;
            } else if (url.includes('ncip-nakiisa-sa-forum-para-sa-kababaihan-mula-sa-ibat-ibang-sektor')) {
                announcementId = 3;
            }
            
            if (announcementId > 0) {
                viewCount.setAttribute('data-announcement-id', announcementId);
            }
        }
    });
    
    // Set interval to refresh comments every 30 seconds
    setInterval(autoRefreshRecentComments, 30000);
    
    // Also set interval to refresh popular readings
    setInterval(updatePopularReadings, 30000);
});

// Function to set up initial comment visibility
function setupCommentVisibility(specificContainer = null) {
    const containers = specificContainer ? 
        [specificContainer] : 
        document.querySelectorAll('.comments-container, #recent-comments-container');

    containers.forEach(container => {
        const isRecent = container.id === 'recent-comments-container';
        const comments = container.querySelectorAll(isRecent ? '.recent-comment-item' : '.comment-item');
        const commentCount = comments.length;

        if (commentCount > 2) {
            
            comments.forEach((comment, index) => {
                if (index >= 2) {
                    comment.classList.add('hidden');
                    comment.classList.remove('visible');
                } else {
                    comment.classList.remove('hidden');
                    comment.classList.add('visible');
                }
            });

            // Remove existing button if any
            const existingButton = container.querySelector('.show-more-comments, .show-less-comments');
            if (existingButton) {
                existingButton.remove();
            }
            
            // Add new "Show more" button
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'show-more-comments';
            button.textContent = `Show more (${commentCount - 2} more)`;
            container.appendChild(button);
        } else {
            // Remove any show more/less buttons if not needed
            const buttons = container.querySelectorAll('.show-more-comments, .show-less-comments');
            buttons.forEach(btn => btn.remove());
        }
    });
}

function autoRefreshRecentComments() {
    console.log('Fetching recent comments...');
    
    fetch('get_recent_comments.php')
        .then(response => response.text())
        .then(html => {
            const recentCommentsContainer = document.getElementById('recent-comments-container');
            if (recentCommentsContainer) {
                recentCommentsContainer.innerHTML = html;
                setupCommentVisibility(recentCommentsContainer); 
                console.log('Recent comments updated successfully');

                fixProfileImages();
                
                document.querySelectorAll('img.avatar, img.photo').forEach(img => {
                    img.onerror = function() {
                        this.src = 'images/gavatar.png';
                        this.onerror = null;
                    };
                });
            }
        })
        .catch(error => {
            console.error('Error fetching recent comments:', error);
        });
}

// Add this function to your announcement.js
function fixBrokenImages() {
    document.querySelectorAll('.recent-post .hentry-post img, .thumb-img img').forEach(img => {
        img.onerror = function() {
            console.log('Image failed to load, replacing with default:', this.src);
            this.src = 'images/default-thumbnail.jpg';
            this.onerror = null; // Prevent infinite loop
        };
        
        // If image is already broken, fix it now
        if (img.complete && (img.naturalWidth === 0 || img.naturalHeight === 0)) {
            console.log('Fixing already broken image:', img.src);
            img.src = 'images/default-thumbnail.jpg';
        }
    });
}

// Call this function after the DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    fixBrokenImages();
    
    // Also call it after content updates via AJAX
    // (You should call fixBrokenImages() after any AJAX operation that updates images)
});