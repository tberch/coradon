// Death Counter with real data updated for Colorado
function updateDeathCounters() {
    // Constants for death rates based on EPA data
    const US_ANNUAL_DEATHS = 21000;
    const COLORADO_ANNUAL_DEATHS = 500;
    const DAYS_IN_YEAR = 365;
    
    // Calculate daily rates
    const US_DAILY_RATE = US_ANNUAL_DEATHS / DAYS_IN_YEAR;
    const COLORADO_DAILY_RATE = COLORADO_ANNUAL_DEATHS / DAYS_IN_YEAR;
    
    // Start date: January 1, 2025
    const startDate = new Date('2025-01-01T00:00:00');
    const currentDate = new Date();
    
    // Calculate time difference in days
    const timeDiff = currentDate - startDate;
    const daysDiff = timeDiff / (1000 * 60 * 60 * 24);
    
    // Calculate estimated deaths
    const usDeaths = Math.floor(daysDiff * US_DAILY_RATE);
    const coloradoDeaths = Math.floor(daysDiff * COLORADO_DAILY_RATE);
    
    // Debug logging
    console.log('Updating death counters...');
    console.log('Days elapsed:', daysDiff);
    console.log('US Deaths:', usDeaths);
    console.log('Colorado Deaths:', coloradoDeaths);
    
    // Update displays
    const usCounter = document.getElementById('usDeathCounter');
    const coloradoCounter = document.getElementById('coloradoDeathCounter');
    
    if (usCounter) {
        usCounter.textContent = usDeaths.toLocaleString();
        console.log('US counter updated');
    } else {
        console.error('US counter element not found');
    }
    
    if (coloradoCounter) {
        coloradoCounter.textContent = coloradoDeaths.toLocaleString();
        console.log('Colorado counter updated');
    } else {
        console.error('Colorado counter element not found');
    }
}

// Initialize counter when components are loaded
function initializeRadonCounter() {
    console.log('Initializing radon counter...');
    
    // Check if elements exist before starting
    const checkAndStart = () => {
        const usCounter = document.getElementById('usDeathCounter');
        const coloradoCounter = document.getElementById('coloradoDeathCounter');
        
        if (usCounter && coloradoCounter) {
            console.log('Counter elements found, starting updates');
            updateDeathCounters();
            // Update once per minute (60000 ms)
            setInterval(updateDeathCounters, 60000);
        } else {
            console.log('Counter elements not found yet, retrying...');
            setTimeout(checkAndStart, 500);
        }
    };
    
    // Start checking after a short delay
    setTimeout(checkAndStart, 2000);
}

// Start initialization when page loads
window.addEventListener('load', initializeRadonCounter);