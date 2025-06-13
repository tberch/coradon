// Shopping Cart functionality
class ShoppingCart {
    constructor() {
        this.cart = JSON.parse(localStorage.getItem('radonCart')) || [];
        this.updateCartCount();
    }
    
    addItem(id, name, price) {
        const existingItemIndex = this.cart.findIndex(item => item.name === name);
        
        if (existingItemIndex === -1) {
            this.cart.push({ 
                name: name, 
                price: parseFloat(price)
            });
            this.saveCart();
            this.showMessage(`${name} has been added to your cart.`, 'success');
        } else {
            this.showMessage(`${name} is already in your cart.`, 'error');
        }
    }
    
    removeItem(index) {
        if (index >= 0 && index < this.cart.length) {
            const removedItem = this.cart[index].name;
            this.cart.splice(index, 1);
            this.saveCart();
            this.showMessage(`${removedItem} has been removed from your cart.`, 'success');
        }
    }
    
    clearCart() {
        this.cart = [];
        this.saveCart();
        this.showMessage('Your cart has been cleared.', 'success');
    }
    
    calculateTotal() {
        return this.cart.reduce((total, item) => total + parseFloat(item.price), 0);
    }
    
    saveCart() {
        localStorage.setItem('radonCart', JSON.stringify(this.cart));
        this.renderCart();
        this.updateCartCount();
    }
    
    renderCart() {
        const cartElement = document.querySelector('.shopping-cart');
        
        if (!cartElement) return;
        
        if (this.cart.length === 0) {
            cartElement.innerHTML = '<p class="cart-empty">Your cart is empty. Browse our services to add items to your cart.</p>';
            return;
        }
        
        const total = this.calculateTotal();
        
        let html = `
            <table class="cart-table">
                <thead>
                    <tr>
                        <th>Service</th>
                        <th>Price</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
        `;
        
        this.cart.forEach((item, index) => {
            html += `
                <tr>
                    <td>${item.name}</td>
                    <td>$${parseFloat(item.price).toFixed(2)}</td>
                    <td>
                        <button class="btn btn-danger remove-item" data-index="${index}">Remove</button>
                    </td>
                </tr>
            `;
        });
        
        html += `
                </tbody>
            </table>
            
            <div class="cart-total">
                Total: $${total.toFixed(2)}
            </div>
            
            <div class="cart-actions">
                <button class="btn btn-danger clear-cart">Clear Cart</button>
                <a href="checkout.html" class="btn checkout-btn">Proceed to Checkout</a>
            </div>
        `;
        
        cartElement.innerHTML = html;
        
        // Add event listeners for the newly created buttons
        document.querySelectorAll('.remove-item').forEach(button => {
            button.addEventListener('click', (e) => {
                const index = parseInt(e.target.dataset.index);
                this.removeItem(index);
            });
        });
        
        const clearCartBtn = document.querySelector('.clear-cart');
        if (clearCartBtn) {
            clearCartBtn.addEventListener('click', () => {
                this.clearCart();
            });
        }
    }
    
    updateCartCount() {
        const cartCount = document.querySelector('.cart-count');
        if (cartCount) {
            cartCount.textContent = this.cart.length;
        }
    }
    
    showMessage(message, type) {
        const messageContainer = document.createElement('div');
        messageContainer.className = `toast-message toast-${type}`;
        messageContainer.textContent = message;
        
        document.body.appendChild(messageContainer);
        
        // Trigger reflow to enable CSS transition
        messageContainer.offsetWidth;
        
        // Show message
        messageContainer.classList.add('show');
        
        // Hide and remove message after 3 seconds
        setTimeout(() => {
            messageContainer.classList.remove('show');
            
            setTimeout(() => {
                messageContainer.remove();
            }, 300); // Wait for fade out transition to complete
        }, 3000);
    }
}

// Initialize cart and make it globally available
window.cart = new ShoppingCart();

// Export for use in other modules
export { ShoppingCart };