// Main JavaScript file

// Radon facts for the slider
const radonFacts = [
    "Colorado has some of the highest radon levels in the United States due to its unique geology.",
    "Approximately 50% of Colorado homes tested have radon levels at or above the EPA action level of 4 pCi/L.",
    "The average indoor radon concentration in Colorado is 6.8 pCi/L, well above the EPA action level.",
    "Colorado's uranium-rich geology and high altitude contribute to elevated radon levels.",
    "Many counties in Colorado are classified as EPA Radon Zone 1, indicating high radon potential.",
    "Colorado building codes now require radon-resistant construction in new homes in certain areas.",
    "The Colorado Department of Public Health recommends all homes be tested for radon.",
    "Radon causes an estimated 500+ deaths per year in Colorado alone.",
    "Colorado's geology with uranium-rich rocks makes our state particularly susceptible to high radon levels.",
    "Testing is the only way to know if your Colorado home has elevated radon levels."
];

let currentFactIndex = 0;

function changeFact() {
    const factElement = document.getElementById('radon-fact');
    if (factElement) {
        factElement.textContent = radonFacts[currentFactIndex];
        currentFactIndex = (currentFactIndex + 1) % radonFacts.length;
    }
}

// Mobile Menu Toggle
function setupMobileMenu() {
    const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
    if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', function() {
            document.querySelector('nav ul').classList.toggle('active');
        });
    }
}

// Smooth Scrolling
function setupSmoothScrolling() {
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({ behavior: 'smooth' });
            }
            
            // Close mobile menu if open
            document.querySelector('nav ul').classList.remove('active');
        });
    });
}

// Setup add to cart buttons
function setupAddToCartButtons() {
    document.querySelectorAll('.add-to-cart').forEach(button => {
        button.addEventListener('click', (e) => {
            const id = e.currentTarget.getAttribute('data-id');
            const name = e.currentTarget.getAttribute('data-name');
            const price = e.currentTarget.getAttribute('data-price');
            
            if (id && name && price && window.cart) {
                window.cart.addItem(id, name, price);
            }
        });
    });
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    // Wait for components to load
    setTimeout(() => {
        // Initialize radon facts slider
        changeFact();
        setInterval(changeFact, 8000);
        
        // Setup mobile menu
        setupMobileMenu();
        
        // Setup smooth scrolling
        setupSmoothScrolling();
        
        // Setup add to cart buttons
        setupAddToCartButtons();
        
        // Render cart if it exists
        if (window.cart) {
            window.cart.renderCart();
        }
    }, 1000);
});