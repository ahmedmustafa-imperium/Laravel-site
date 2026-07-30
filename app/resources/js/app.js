import '../css/responsive.css';

const ready = (callback) => document.readyState === 'loading'
    ? document.addEventListener('DOMContentLoaded', callback)
    : callback();

ready(() => {
    const body = document.body;
    const backdrop = document.querySelector('[data-drawer-backdrop]');
    const mobileMenu = document.querySelector('[data-mobile-menu]');
    const filterPanel = document.querySelector('[data-filter-panel]');

    const setBackdrop = (visible) => {
        if (!backdrop) return;
        backdrop.hidden = !visible;
        body.classList.toggle('drawer-open', visible);
    };
    const closeDrawers = () => {
        mobileMenu?.classList.remove('open');
        mobileMenu?.setAttribute('aria-hidden', 'true');
        filterPanel?.classList.remove('open');
        document.querySelector('.admin-sidebar')?.classList.remove('open');
        setBackdrop(false);
    };
    document.querySelector('[data-menu-toggle]')?.addEventListener('click', () => {
        mobileMenu?.classList.add('open');
        mobileMenu?.setAttribute('aria-hidden', 'false');
        setBackdrop(true);
    });
    document.querySelector('[data-menu-close]')?.addEventListener('click', closeDrawers);
    document.querySelector('[data-filter-open]')?.addEventListener('click', () => {
        filterPanel?.classList.add('open');
        setBackdrop(true);
    });
    document.querySelector('[data-filter-close]')?.addEventListener('click', closeDrawers);
    backdrop?.addEventListener('click', closeDrawers);
    document.addEventListener('keydown', (event) => event.key === 'Escape' && closeDrawers());

    const headerSearch = document.querySelector('.header-search');
    document.querySelector('[data-mobile-search]')?.addEventListener('click', () => {
        headerSearch?.classList.toggle('open');
        setTimeout(() => headerSearch?.querySelector('input')?.focus(), 40);
    });

    document.querySelectorAll('[data-flash-close]').forEach((button) => button.addEventListener('click', () => button.closest('.flash')?.remove()));
    setTimeout(() => document.querySelector('[data-flash]')?.remove(), 7000);

    const toast = document.querySelector('[data-toast]');
    let toastTimer;
    const showToast = (message) => {
        if (!toast) return;
        toast.textContent = message;
        toast.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => toast.classList.remove('show'), 2200);
    };

    // Predictive product search.
    const searchInput = document.querySelector('[data-product-search]');
    const searchResults = document.querySelector('[data-search-results]');
    let searchTimer;
    searchInput?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        const query = searchInput.value.trim();
        if (query.length < 2) {
            if (searchResults) searchResults.hidden = true;
            return;
        }
        searchTimer = setTimeout(async () => {
            try {
                const response = await fetch(`/search/suggestions?q=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' } });
                if (!response.ok) return;
                const products = await response.json();
                if (!searchResults) return;
                searchResults.replaceChildren();
                products.forEach((product) => {
                    const link = document.createElement('a');
                    link.href = product.url;
                    const image = document.createElement('img');
                    image.src = product.image;
                    image.alt = '';
                    const name = document.createElement('strong');
                    name.textContent = product.name;
                    const price = document.createElement('span');
                    price.textContent = `AED ${product.price}`;
                    link.append(image, name, price);
                    searchResults.append(link);
                });
                if (!products.length) {
                    const empty = document.createElement('p');
                    empty.textContent = 'No matching products yet.';
                    searchResults.append(empty);
                }
                searchResults.hidden = false;
            } catch (_) {
                if (searchResults) searchResults.hidden = true;
            }
        }, 260);
    });
    document.addEventListener('click', (event) => {
        if (!event.target.closest('[data-search-wrapper]') && searchResults) searchResults.hidden = true;
    });

    // Local wishlist: deliberately browser-local until a customer signs in.
    const readWishlist = () => {
        try { return JSON.parse(localStorage.getItem('snh-wishlist') || '{}'); } catch (_) { return {}; }
    };
    const updateWishlistUI = () => {
        const wishlist = readWishlist();
        document.querySelectorAll('[data-wishlist-count]').forEach((counter) => counter.textContent = Object.keys(wishlist).length);
        document.querySelectorAll('[data-wishlist-item]').forEach((button) => {
            button.classList.toggle('saved', Boolean(wishlist[button.dataset.wishlistItem]));
            button.textContent = wishlist[button.dataset.wishlistItem] ? '♥' : '♡';
        });
    };
    document.querySelectorAll('[data-wishlist-item]').forEach((button) => button.addEventListener('click', () => {
        const wishlist = readWishlist();
        const id = button.dataset.wishlistItem;
        if (wishlist[id]) {
            delete wishlist[id];
            showToast('Removed from your wishlist');
        } else {
            wishlist[id] = button.dataset.wishlistName || 'Saved product';
            showToast('Saved to your wishlist');
        }
        localStorage.setItem('snh-wishlist', JSON.stringify(wishlist));
        updateWishlistUI();
    }));
    document.querySelector('[data-wishlist-open]')?.addEventListener('click', () => {
        const names = Object.values(readWishlist());
        showToast(names.length ? `${names.length} saved item${names.length === 1 ? '' : 's'} in this browser` : 'Your wishlist is empty');
    });
    updateWishlistUI();

    // Quantity, variant and image gallery interactions.
    document.querySelectorAll('[data-qty-minus],[data-qty-plus]').forEach((button) => button.addEventListener('click', () => {
        const stepper = button.closest('.quantity-stepper');
        const input = stepper?.querySelector('input');
        if (!input) return;
        const min = Number(input.min || 1);
        const max = Number(input.max || 999);
        const delta = button.hasAttribute('data-qty-plus') ? 1 : -1;
        input.value = String(Math.max(min, Math.min(max, Number(input.value || min) + delta)));
        if (input.closest('.cart-line')) input.dispatchEvent(new Event('change', { bubbles: true }));
    }));
    document.querySelectorAll('[data-gallery-thumb]').forEach((button) => button.addEventListener('click', () => {
        const main = document.querySelector('[data-gallery-main]');
        if (main) main.src = button.dataset.galleryThumb;
    }));
    document.querySelectorAll('[data-variant-price]').forEach((radio) => radio.addEventListener('change', () => {
        const price = `AED ${Number(radio.dataset.variantPrice).toFixed(2)}`;
        document.querySelector('[data-product-price]')?.replaceChildren(document.createTextNode(price));
        document.querySelector('[data-button-price]')?.replaceChildren(document.createTextNode(price));
    }));

    document.querySelectorAll('[data-grid-view]').forEach((button) => button.addEventListener('click', () => {
        const mode = button.dataset.gridView;
        document.querySelector('[data-product-grid]')?.classList.toggle('list', mode === 'list');
        document.querySelectorAll('[data-grid-view]').forEach((item) => item.classList.toggle('active', item === button));
    }));

    document.querySelectorAll('[data-password-toggle]').forEach((button) => button.addEventListener('click', () => {
        const input = button.parentElement?.querySelector('input');
        if (!input) return;
        input.type = input.type === 'password' ? 'text' : 'password';
        button.textContent = input.type === 'password' ? 'Show' : 'Hide';
    }));

    // First-visit offer; sessionStorage avoids repeatedly interrupting a visit.
    const welcomeModal = document.querySelector('[data-welcome-modal]');
    if (welcomeModal && !sessionStorage.getItem('snh-welcome-seen')) {
        setTimeout(() => {
            welcomeModal.showModal?.();
            sessionStorage.setItem('snh-welcome-seen', '1');
        }, 1100);
    }
    document.querySelector('[data-modal-close]')?.addEventListener('click', () => welcomeModal?.close());
    document.querySelector('[data-copy-coupon]')?.addEventListener('click', async () => {
        try { await navigator.clipboard.writeText('WELCOME10'); } catch (_) { /* clipboard may be unavailable on HTTP */ }
        showToast('WELCOME10 copied');
        welcomeModal?.close();
    });

    document.querySelector('[data-admin-menu]')?.addEventListener('click', () => {
        document.querySelector('.admin-sidebar')?.classList.toggle('open');
    });

    const heroImage = document.querySelector('.hero picture img');
    heroImage?.addEventListener('error', () => {
        heroImage.closest('picture').style.display = 'none';
        const fallback = document.querySelector('.hero__fallback');
        if (fallback) {
            fallback.style.position = 'relative';
            fallback.style.inset = 'auto';
            fallback.style.padding = '80px 7%';
            fallback.style.transform = 'none';
            fallback.style.visibility = 'visible';
        }
    });

    document.querySelector('#checkout-form')?.addEventListener('submit', (event) => {
        const button = event.currentTarget.querySelector('.checkout-submit');
        if (button) {
            button.disabled = true;
            button.textContent = 'Creating your order…';
        }
    });
});
