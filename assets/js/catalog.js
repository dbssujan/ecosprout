document.addEventListener('DOMContentLoaded', () => {
    let currentCategory = 'all';

    const searchInput = document.getElementById('searchPlant') || document.getElementById('plantSearchInput');
    const filterPills = document.querySelectorAll('.filter-pill') || document.querySelectorAll('[data-category]');
    const grid = document.getElementById('plantGridContainer');

    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.trim();
            fetchPlants(query, currentCategory);
        });
    }

    if (filterPills.length > 0) {
        filterPills.forEach(pill => {
            pill.addEventListener('click', (e) => {
                filterPills.forEach(p => p.classList.remove('active'));
                e.currentTarget.classList.add('active');
                currentCategory = e.currentTarget.dataset.category || 'all';
                const query = searchInput ? searchInput.value.trim() : '';
                fetchPlants(query, currentCategory);
            });
        });
    }

    async function fetchPlants(search = '', category = 'all') {
        if (!grid) return;
        grid.style.opacity = '0.5';

        try {
            const url = new URL('../api/plants.php', window.location.href);
            if (search) url.searchParams.append('q', search);
            if (category && category !== 'all') url.searchParams.append('category', category);

            const res = await fetch(url);
            const data = await res.json();
            renderPlantGrid(data);
        } catch (error) {
            console.error("Error fetching plants:", error);
        } finally {
            grid.style.opacity = '1';
        }
    }

    function renderPlantGrid(plants) {
        if (!plants || plants.length === 0) {
            grid.innerHTML = '<div style="grid-column: 1 / -1; text-align: center; padding: 3rem; font-size: 1.2rem; color: #666;">No plants found matching your search.</div>';
            return;
        }

        grid.innerHTML = plants.map(plant => `
            <div class="plant-card" style="background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); display: flex; flex-direction: column; transition: transform 0.2s, box-shadow 0.2s; height: 100%;">
                
                <a href="plant_details.php?id=${plant.id}" style="text-decoration: none; color: inherit; display: flex; flex-direction: column; flex-grow: 1;">
                    <div class="plant-img-container" style="position: relative; height: 200px; overflow: hidden; background: #f9fafb;">
                        <img src="../${plant.image_url || 'assets/img/default_plant.png'}" 
                             alt="${plant.name}" 
                             style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s;"
                             onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                        ${plant.stock_quantity > 0 
                            ? `<span style="position: absolute; top: 10px; right: 10px; background: #10b981; color: white; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: bold; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">In Stock</span>`
                            : `<span style="position: absolute; top: 10px; right: 10px; background: #ef4444; color: white; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: bold; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">Out of Stock</span>`
                        }
                    </div>

                    <div class="plant-info" style="padding: 1.25rem; display: flex; flex-direction: column; flex-grow: 1;">
                        <span class="plant-category-tag" style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #059669; letter-spacing: 0.05em; margin-bottom: 0.3rem; display: block;">
                            ${plant.category_name || 'Plant'}
                        </span>
                        <h3 class="plant-title" style="font-size: 1.1rem; font-weight: 700; color: #111827; margin: 0 0 0.25rem 0;">
                            ${plant.name}
                        </h3>
                        
                        ${(plant.category_slug !== 'tools-accessories' && plant.care_sunlight && plant.care_sunlight !== 'Not Applicable' && plant.care_sunlight !== 'N/A') ? `
                            <div class="plant-care-badges" style="display: flex; gap: 6px; flex-wrap: wrap; margin-top: 0.5rem;">
                                <span class="care-badge" style="background: #fef3c7; color: #d97706; padding: 3px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 500;">
                                    ${plant.care_sunlight}
                                </span>
                                <span class="care-badge" style="background: #e0f2fe; color: #0284c7; padding: 3px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 500;">
                                    ${plant.care_water || 'Moderate'}
                                </span>
                            </div>
                        ` : ''}
                    </div>
                </a>

                <div class="plant-footer" style="padding: 1rem 1.25rem 1.25rem 1.25rem; border-top: 1px solid #f3f4f6; margin-top: auto; display: flex; flex-direction: column; gap: 10px;">
                    <div class="plant-price" style="font-weight: 700; font-size: 1.25rem; color: #111827;">
                        LKR ${parseFloat(plant.price).toFixed(2)}
                    </div>

                    <button class="btn btn-primary" onclick="addToCart(${plant.id}, 1)" 
                            style="width: 100%; text-align: center; background: #065f46; color: white; padding: 10px 0; font-size: 0.9rem; font-weight: 600; border-radius: 6px; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px; transition: 0.2s;"
                            onmouseover="this.style.background='#047857'" onmouseout="this.style.background='#065f46'">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                        Add to Cart
                    </button>
                </div>
            </div>
        `).join('');
    }
});