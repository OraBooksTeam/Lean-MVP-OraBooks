document.addEventListener('DOMContentLoaded', function() {
    
    // Helper to show messages
    const showMessage = (elementId, message, isError = false) => {
        const msgDiv = document.getElementById(elementId);
        if(!msgDiv) return;
        msgDiv.textContent = message;
        msgDiv.classList.remove('hidden', 'text-green-600', 'text-red-600', 'bg-green-100', 'bg-red-100');
        msgDiv.classList.add(isError ? 'text-red-600' : 'text-green-600');
        msgDiv.classList.add(isError ? 'bg-red-100' : 'bg-green-100');
        msgDiv.classList.add('rounded-lg');
    };

    // Sidebar Toggle for submenus is already in dashboard.php inline script
    // If there were any other general inventory JS, it would go here.

});
