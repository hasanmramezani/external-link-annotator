document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.ela-marker, .ela-link').forEach(element => {
        element.addEventListener('click', function(e) {
            e.preventDefault();
            const refNumber = this.getAttribute('data-number');
            const target = document.querySelector(`#ref-${refNumber}`);
            if(target) {
                const offsetTop = target.getBoundingClientRect().top + window.scrollY - 50; // Adjust offset for better scroll position
                window.scrollTo({
                    top: offsetTop,
                    behavior: 'smooth'
                });

                // Remove previous highlights
                document.querySelectorAll('#ela-references li').forEach(li => {
                    li.classList.remove('highlight');
                });

                // Add highlight effect
                target.classList.add('highlight');
            }
        });
    });

    // Initialize tippy.js for tooltips
    tippy('.ela-link', {
        content: (reference) => reference.getAttribute('data-tippy-content'),
        theme: 'light',
    });
});
