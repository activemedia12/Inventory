// Mobile Menu Toggle
const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
        console.log(entry)
        if (entry.isIntersecting) {
            entry.target.classList.add('show');
        } else {
            entry.target.classList.remove('show');
        }
    });
});

const hiddenElements = document.querySelectorAll('.hide');
hiddenElements.forEach((el) => observer.observe(el));

document.addEventListener('DOMContentLoaded', function () {
    const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
    const navLinks = document.querySelector('.nav-links');

    if (mobileMenuToggle) {
        mobileMenuToggle.addEventListener('click', function () {
            navLinks.classList.toggle('active');
            this.querySelector('i').classList.toggle('fa-bars');
            this.querySelector('i').classList.toggle('fa-times');
        });
    }

    // Filtering functionality for All Services section
    const categoryFilter = document.getElementById('category-filter');
    const sortFilter = document.getElementById('sort-filter');
    const searchInput = document.getElementById('search-input');
    const searchBtn = document.getElementById('search-btn');
    const productCards = document.querySelectorAll('.all-services-section .product-card');

    // Add event listeners for filtering
    if (categoryFilter) {
        categoryFilter.addEventListener('change', filterProducts);
    }

    if (sortFilter) {
        sortFilter.addEventListener('change', filterProducts);
    }

    if (searchBtn) {
        searchBtn.addEventListener('click', filterProducts);
    }

    if (searchInput) {
        searchInput.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                filterProducts();
            }
        });
    }

    // Handle View All clicks
    const viewAllLinks = document.querySelectorAll('.view-all[data-category]');

    viewAllLinks.forEach(link => {
        link.addEventListener('click', function (e) {
            e.preventDefault();

            const category = this.getAttribute('data-category');
            const allServicesSection = document.getElementById('all-services');

            // Scroll to the All Services section
            allServicesSection.scrollIntoView({ behavior: 'smooth' });

            // Set the category filter
            if (categoryFilter) {
                categoryFilter.value = category;
            }

            // Trigger filtering
            filterProducts();
        });
    });

    function filterProducts() {
        const categoryValue = categoryFilter ? categoryFilter.value : 'all';
        const sortValue = sortFilter ? sortFilter.value : 'name';
        const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';

        // First filter by category and search term
        let visibleCards = [];

        productCards.forEach(card => {
            const category = card.getAttribute('data-category');
            const name = card.querySelector('.product-name').textContent.toLowerCase();
            const description = card.querySelector('.product-category').textContent.toLowerCase();

            let categoryMatch = categoryValue === 'all' || category === categoryValue;
            let searchMatch = name.includes(searchTerm) || description.includes(searchTerm);

            if (categoryMatch && searchMatch) {
                card.style.display = 'flex';
                visibleCards.push(card);
            } else {
                card.style.display = 'none';
            }
        });

        // Then sort if needed
        if (sortValue === 'name') {
            sortByName(visibleCards);
        } else if (sortValue === 'category') {
            sortByCategory(visibleCards);
        } else if (sortValue === 'price-low') {
            sortByPrice(visibleCards, 'asc');
        } else if (sortValue === 'price-high') {
            sortByPrice(visibleCards, 'desc');
        }
    }

    function sortByName(cards) {
        const container = document.querySelector('.all-services-section .products-grid');

        // Sort cards by name
        cards.sort((a, b) => {
            const nameA = a.querySelector('.product-name').textContent;
            const nameB = b.querySelector('.product-name').textContent;
            return nameA.localeCompare(nameB);
        });

        // Reattach sorted cards
        cards.forEach(card => {
            container.appendChild(card);
        });
    }

    function sortByCategory(cards) {
        const container = document.querySelector('.all-services-section .products-grid');

        // Sort cards by category
        cards.sort((a, b) => {
            const categoryA = a.getAttribute('data-category');
            const categoryB = b.getAttribute('data-category');
            return categoryA.localeCompare(categoryB);
        });

        // Reattach sorted cards
        cards.forEach(card => {
            container.appendChild(card);
        });
    }

    function sortByPrice(cards, order) {
        const container = document.querySelector('.all-services-section .products-grid');

        // Sort cards by price
        cards.sort((a, b) => {
            const priceA = parseFloat(a.querySelector('.product-price').textContent.replace('₱', '').replace(',', ''));
            const priceB = parseFloat(b.querySelector('.product-price').textContent.replace('₱', '').replace(',', ''));

            return order === 'asc' ? priceA - priceB : priceB - priceA;
        });

        // Reattach sorted cards
        cards.forEach(card => {
            container.appendChild(card);
        });
    }

    // Initialize filtering on page load
    filterProducts();
});