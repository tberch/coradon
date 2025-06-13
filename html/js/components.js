// Component loader
export async function loadComponent(elementId, componentPath) {
    try {
        // Remove leading slash for relative path
        const relativePath = componentPath.startsWith('/') ? componentPath.substring(1) : componentPath;
        const response = await fetch(relativePath);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const html = await response.text();
        const element = document.getElementById(elementId);
        if (element) {
            element.innerHTML = html;
            
            // Execute any scripts in the loaded component
            const scripts = element.querySelectorAll('script');
            scripts.forEach(script => {
                const newScript = document.createElement('script');
                newScript.textContent = script.textContent;
                script.parentNode.replaceChild(newScript, script);
            });
        }
    } catch (error) {
        console.error(`Error loading component ${componentPath}:`, error);
        // Show error in the element
        const element = document.getElementById(elementId);
        if (element) {
            element.innerHTML = `<p style="color: red;">Error loading component: ${error.message}</p>`;
        }
    }
}

// Initialize components
export async function initializeComponents() {
    // Load all components
    const components = [
        { id: 'navigation', path: 'components/navigation.html' },
        { id: 'cart-modal-container', path: 'components/cart-modal.html' },
        { id: 'hero-section', path: 'components/hero.html' },
        { id: 'stats-section', path: 'components/stats.html' },
        { id: 'testing-section', path: 'components/testing.html' },
        { id: 'mitigation-section', path: 'components/mitigation.html' },
        { id: 'benefits-section', path: 'components/benefits.html' },
        { id: 'colorado-stats-section', path: 'components/colorado-stats.html' },
        { id: 'services-section', path: 'components/services.html' },
        { id: 'contact-section', path: 'components/contact.html' },
        { id: 'footer-content', path: 'components/footer.html' }
    ];
    
    // Load all components in parallel
    await Promise.all(components.map(comp => loadComponent(comp.id, comp.path)));
    
    // Initialize cart modal functionality after loading
    setupCartModal();
    
    // Re-initialize cart display after modal loads
    if (window.cart) {
        window.cart.renderCart();
    }
    
    // Remove loading class to show content
    document.body.classList.remove('loading');
}

// Setup cart modal functionality
function setupCartModal() {
    const modal = document.getElementById('cart-modal');
    const cartToggle = document.getElementById('cart-toggle');
    const closeBtn = document.querySelector('.modal .close');
    
    if (cartToggle) {
        cartToggle.addEventListener('click', (e) => {
            e.preventDefault();
            if (modal) modal.style.display = 'block';
        });
    }
    
    if (closeBtn) {
        closeBtn.addEventListener('click', () => {
            if (modal) modal.style.display = 'none';
        });
    }
    
    // Close modal when clicking outside
    window.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.style.display = 'none';
        }
    });
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', initializeComponents);