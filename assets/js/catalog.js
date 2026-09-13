let debounceTimer = null;
let currentCategory = 'all';

document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('plantSearchInput');
    const filterPills = document.querySelectorAll('.filter-pill');

    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                fetchPlants(e.target.value, currentCategory);
            }, 300);
        });
    }

    filterPills.forEach(pill => {
        pill.addEventListener('click', () => {
            filterPills.forEach(p => p.classList.remove('active'));
            pill.classList.add('active');
            currentCategory = pill.dataset.category;
            const query = searchInput ? searchInput.value : '';
            fetchPlants(query, currentCategory);
        });
    });
});

async function fetchPlants(search = '', category = 'all') {
    const grid = document.getElementById('plantGridContainer');
    if (!grid) return;

    grid.style.opacity = '0.5';

    try {
        const url = new URL(`${APP_URL}/api/plants.php`);
        if (search) url.searchParams.append('q', search);
        if (category && category !== 'all') url.searchParams.append('category', category);

        const res = await fetch(url);
        const data = await res.json();

        if (data.status === 'success') {
            renderPlantGrid(data.plants);
        } else {
            grid.innerHTML = `<p class="text-muted">No plants found matching your criteria.</p>`;
        }
    } catch (err) {
        console.error('Error fetching plants:', err);
    } finally {
        grid.style.opacity = '1';
    }
}

function renderPlantGrid(plants) {
    const grid = document.getElementById('plantGridContainer');
    if (!grid) return;

    if (!plants || plants.length === 0) {
        grid.innerHTML = `<div style="grid-column: 1/-1; text-align: center; padding: 3rem 0;">
            <h3>No flora found 🌿</h3>
            <p class="text-muted">Try adjusting your search terms or category filters.</p>
        </div>`;
        return;
    }

    grid.innerHTML = plants.map(plant => `
        <div class="plant-card">
            <div class="plant-img-placeholder">🌱</div>
            <div class="plant-info">
                <span class="plant-category-tag">${escapeHtml(plant.category_name || 'Plant')}</span>
                <h3 class="plant-title">${escapeHtml(plant.name)}</h3>
                <p class="botanical-name">${escapeHtml(plant.botanical_name || '')}</p>
                <div class="plant-care-badges">
                    <span class="care-badge">☀️ ${escapeHtml(plant.care_sunlight)}</span>
                    <span class="care-badge">💧 ${escapeHtml(plant.care_water)}</span>
                </div>
                <div class="plant-footer">
                    <span class="plant-price">LKR ${Number(plant.price).toLocaleString()}</span>
                    <button class="btn btn-primary-sm" onclick="addToCart(${plant.id})">Add to Cart</button>
                </div>
            </div>
        </div>
    `).join('');
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>'"]/g, 
        tag => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[tag] || tag)
    );
}
