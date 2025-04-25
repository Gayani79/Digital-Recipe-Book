
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    })
    
    // Newsletter form submission
    document.getElementById('newsletter-form')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const email = this.querySelector('input[type="email"]').value;
        
        // Normally you would send this to the server
        alert('Thank you for subscribing with: ' + email);
        this.reset();
    });
    
    // Add favorite functionality
    document.querySelectorAll('.favorite-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const recipeId = this.dataset.recipeId;
            
            // AJAX request would go here
            this.classList.toggle('active');
            const icon = this.querySelector('i');
            
            if (this.classList.contains('active')) {
                icon.classList.remove('far');
                icon.classList.add('fas');
            } else {
                icon.classList.remove('fas');
                icon.classList.add('far');
            }
        });
    });
    
    // Auto-hide flash messages after 5 seconds
    setTimeout(function() {
        const flashMessages = document.querySelectorAll('.alert-dismissible');
        flashMessages.forEach(message => {
            const bsAlert = new bootstrap.Alert(message);
            bsAlert.close();
        });
    }, 5000);
