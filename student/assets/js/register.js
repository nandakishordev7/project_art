// Digital Art School - Registration JavaScript

// Art disciplines mapped to categories
const artDisciplines = {
    "Classical Dance": [
        "Kathakali",
        "Mohiniyattam",
        "Bharatanatyam",
        "Kuchipudi",
        "Odissi"
    ],
    "Vocal Music": [
        "Carnatic Vocal",
        "Hindustani Classical",
        "Kathakali Sangeetham",
        "Light Music",
        "Devotional"
    ],
    "Instrumental Music": [
        "Veena",
        "Mridangam",
        "Tabla",
        "Violin",
        "Flute"
    ],
    "Visual Arts": [
        "Mural Painting",
        "Traditional Painting",
        "Sculpture",
        "Pottery",
        "Textile Arts"
    ]
};

// Update art disciplines based on selected category
function updateDisciplines() {
    const categorySelect = document.getElementById('art_category');
    const disciplineSelect = document.getElementById('art_discipline');
    const selectedCategory = categorySelect.value;
    
    // Clear existing options
    disciplineSelect.innerHTML = '<option value="">Select discipline...</option>';
    
    if (selectedCategory && artDisciplines[selectedCategory]) {
        artDisciplines[selectedCategory].forEach(discipline => {
            const option = document.createElement('option');
            option.value = discipline;
            option.textContent = discipline;
            disciplineSelect.appendChild(option);
        });
    }
}

// Detect user location using browser geolocation API
function detectLocation() {
    const locationInput = document.getElementById('location');
    const button = event.target.closest('.btn-detect-location');
    
    if (!navigator.geolocation) {
        alert('Geolocation is not supported by your browser');
        return;
    }
    
    button.disabled = true;
    button.textContent = 'Detecting...';
    locationInput.value = 'Detecting location...';
    
    navigator.geolocation.getCurrentPosition(
        // Success callback
        async function(position) {
            const lat = position.coords.latitude;
            const lon = position.coords.longitude;
            
            try {
                // Use reverse geocoding to get location name
                const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lon}`);
                const data = await response.json();
                
                // Extract city, state, country
                const city = data.address.city || data.address.town || data.address.village || '';
                const state = data.address.state || '';
                const country = data.address.country || '';
                
                let locationString = '';
                if (city) locationString += city;
                if (state) locationString += (locationString ? ', ' : '') + state;
                if (country) locationString += (locationString ? ', ' : '') + country;
                
                locationInput.value = locationString || 'Location detected';
                locationInput.removeAttribute('readonly');
            } catch (error) {
                console.error('Error getting location name:', error);
                locationInput.value = `${lat.toFixed(4)}, ${lon.toFixed(4)}`;
                locationInput.removeAttribute('readonly');
            }
            
            button.disabled = false;
            button.innerHTML = `
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                    <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z" fill="currentColor"/>
                </svg>
                Detect Location
            `;
        },
        // Error callback
        function(error) {
            console.error('Error detecting location:', error);
            locationInput.value = '';
            locationInput.removeAttribute('readonly');
            locationInput.placeholder = 'Enter your location manually';
            
            button.disabled = false;
            button.innerHTML = `
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                    <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z" fill="currentColor"/>
                </svg>
                Detect Location
            `;
            
            alert('Unable to detect location. Please enter manually.');
        }
    );
}

// Handle file selection
function handleFileSelect(input) {
    const label = document.getElementById('file-upload-label');
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const fileSize = (file.size / 1024 / 1024).toFixed(2); // Size in MB
        
        label.classList.add('file-selected');
        label.innerHTML = `
            <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
                <path d="M9 16l6 6 12-12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <div>
                <div style="font-weight: 600;">${file.name}</div>
                <div style="font-size: 13px; color: #6c757d;">${fileSize} MB</div>
            </div>
        `;
    }
}

// Multi-step form navigation
document.addEventListener('DOMContentLoaded', function() {
    let currentStep = 1;
    const totalSteps = 4;
    
    // Get all step elements
    const formSteps = document.querySelectorAll('.form-step');
    const progressSteps = document.querySelectorAll('.progress-step');
    const nextButtons = document.querySelectorAll('.btn-next');
    const prevButtons = document.querySelectorAll('.btn-prev');
    
    // Show specific step
    function showStep(stepNumber) {
        // Hide all steps
        formSteps.forEach(step => {
            step.classList.remove('active');
        });
        
        // Show current step
        const currentStepElement = document.querySelector(`.form-step[data-step="${stepNumber}"]`);
        if (currentStepElement) {
            currentStepElement.classList.add('active');
        }
        
        // Update progress indicators
        progressSteps.forEach((step, index) => {
            const stepNum = index + 1;
            step.classList.remove('active', 'completed');
            
            if (stepNum < stepNumber) {
                step.classList.add('completed');
            } else if (stepNum === stepNumber) {
                step.classList.add('active');
            }
        });
        
        // Scroll to top of form
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    
    // Validate current step
    function validateStep(stepNumber) {
        const currentStepElement = document.querySelector(`.form-step[data-step="${stepNumber}"]`);
        if (!currentStepElement) return true;
        
        const requiredInputs = currentStepElement.querySelectorAll('[required]');
        let isValid = true;
        
        requiredInputs.forEach(input => {
            if (!input.value.trim()) {
                isValid = false;
                input.classList.add('error');
                
                // Add error styling
                if (!input.classList.contains('error-shown')) {
                    input.classList.add('error-shown');
                    input.style.borderColor = '#dc3545';
                }
            } else {
                input.classList.remove('error', 'error-shown');
                input.style.borderColor = '';
            }
        });
        
        // Additional validation for step 1 (passwords match)
        if (stepNumber === 1) {
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            
            if (password.value && confirmPassword.value && password.value !== confirmPassword.value) {
                confirmPassword.style.borderColor = '#dc3545';
                alert('Passwords do not match!');
                isValid = false;
            }
        }
        
        if (!isValid) {
            alert('Please fill in all required fields before proceeding.');
        }
        
        return isValid;
    }
    
    // Next button click
    nextButtons.forEach(button => {
        button.addEventListener('click', function() {
            if (validateStep(currentStep)) {
                if (currentStep < totalSteps) {
                    currentStep++;
                    showStep(currentStep);
                }
            }
        });
    });
    
    // Previous button click
    prevButtons.forEach(button => {
        button.addEventListener('click', function() {
            if (currentStep > 1) {
                currentStep--;
                showStep(currentStep);
            }
        });
    });
    
    // Initialize form
    showStep(currentStep);
    
    // Remove error styling on input
    const allInputs = document.querySelectorAll('input, select, textarea');
    allInputs.forEach(input => {
        input.addEventListener('input', function() {
            this.style.borderColor = '';
            this.classList.remove('error', 'error-shown');
        });
    });
});
