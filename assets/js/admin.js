
jQuery(document).ready(function($) {
    var searchTimeout;
    
    // User search
    $('#user-search').on('keyup', function() {
        var search = $(this).val();
        
        // Clear previous timeout
        clearTimeout(searchTimeout);
        
        // Set new timeout
        searchTimeout = setTimeout(function() {
            if (search.length < 3) {
                $('#search-results').empty();
                return;
            }
            
            // Search for users
            $.ajax({
                url: user_blocker.ajax_url,
                type: 'POST',
                data: {
                    action: 'search_users',
                    nonce: user_blocker.nonce,
                    search: search
                },
                success: function(response) {
                    if (response.success) {
                        displaySearchResults(response.data.results);
                    }
                }
            });
        }, 500);
    });
    
    // Display search results
    function displaySearchResults(results) {
        var html = '';
        
        if (results.length === 0) {
            html = '<p>No users found.</p>';
        } else {
            for (var i = 0; i < results.length; i++) {
                var user = results[i];
                html += '<div class="search-result-item">';
                html += '<div class="user-info">';
                html += '<strong>' + user.username + '</strong> (' + user.email + ')';
                html += '</div>';
                html += '<div class="user-actions">';
                
                if (user.is_admin) {
                    html += '<span class="button disabled">Cannot Block Administrator</span>';
                } else if (user.is_blocked) {
                    html += '<span class="button disabled">Already Blocked</span>';
                } else {
                    html += '<button class="button button-primary block-user" data-user-id="' + user.id + '">Block</button>';
                }
                
                html += '</div>';
                html += '</div>';
            }
        }
        
        $('#search-results').html(html);
    }
    
    // Block user
    $(document).on('click', '.block-user', function() {
        var userId = $(this).data('user-id');
        
        $.ajax({
            url: user_blocker.ajax_url,
            type: 'POST',
            data: {
                action: 'block_user',
                nonce: user_blocker.nonce,
                user_id: userId
            },
            success: function(response) {
                if (response.success) {
                    // Update search results
                    $(this).closest('.search-result-item').find('.user-actions').html('<span class="button disabled">Already Blocked</span>');
                    
                    // Update blocked users list
                    var user = response.data.user;
                    var tableBody = $('.user-blocker-list table tbody');
                    
                    // If table doesn't exist yet, create it
                    if (tableBody.length === 0) {
                        $('.user-blocker-list').html(
                            '<h2>Currently Blocked Users</h2>' +
                            '<table class="wp-list-table widefat fixed striped">' +
                            '<thead><tr><th>Username</th><th>Email</th><th>Blocked On</th><th>Actions</th></tr></thead>' +
                            '<tbody></tbody></table>'
                        );
                        tableBody = $('.user-blocker-list table tbody');
                    }
                    
                    // Add new row
                    var newRow = '<tr id="blocked-user-' + user.id + '">' +
                        '<td>' + user.username + '</td>' +
                        '<td>' + user.email + '</td>' +
                        '<td>' + user.blocked_on + '</td>' +
                        '<td><button class="button unblock-user" data-user-id="' + user.id + '">Unblock</button></td>' +
                        '</tr>';
                    
                    tableBody.append(newRow);
                    
                    // Clear search field
                    $('#user-search').val('');
                    $('#search-results').empty();
                }
            }
        });
    });
    
    // Unblock user
    $(document).on('click', '.unblock-user', function() {
        var userId = $(this).data('user-id');
        var row = $(this).closest('tr');
        
        $.ajax({
            url: user_blocker.ajax_url,
            type: 'POST',
            data: {
                action: 'unblock_user',
                nonce: user_blocker.nonce,
                user_id: userId
            },
            success: function(response) {
                if (response.success) {
                    // Remove row
                    row.remove();
                    
                    // If no more blocked users, show message
                    if ($('.user-blocker-list table tbody tr').length === 0) {
                        $('.user-blocker-list table').remove();
                        $('.user-blocker-list').append('<p>No users are currently blocked.</p>');
                    }
                }
            }
        });
    });
});
