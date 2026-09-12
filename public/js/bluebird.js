/* Blue Bird JS v0.1.0 | MIT License | @seip/blue-bird-css*/
function bluebird(component, options) {
    if (typeof component === 'object') {
        options = component;
        component = 'snackbar';
    }
    if (component === 'snackbar') {
        let snackbarEl = document.getElementById('snackbar');
        if (!snackbarEl) {
            snackbarEl = document.createElement('div');
            snackbarEl.id = 'snackbar';
            document.body.appendChild(snackbarEl);
        }
        snackbarEl.className = 'show';
        if (options && options.type) {
            snackbarEl.classList.add(options.type);
        } else {
            snackbarEl.classList.add('info');
        }
        snackbarEl.textContent = (options && options.message) || '';
        setTimeout(() => {
            snackbarEl.classList.add('show');
        }, 10);
        const duration = (options && options.duration) || 3000;
        if (snackbarEl.timeoutId) {
            clearTimeout(snackbarEl.timeoutId);
        }
        snackbarEl.timeoutId = setTimeout(function () {
            snackbarEl.className = '';
        }, duration);
    }
    if (component === 'toast') {
        const position = (options && options.position) || 'bottom-right';
        let container = document.querySelector(`.toast-container.${position}`);
        if (!container) {
            container = document.createElement('div');
            container.className = `toast-container ${position}`;
            document.body.appendChild(container);
        }
        const toastEl = document.createElement('div');
        const typeClass = (options && options.type) ? `toast-${options.type}` : 'toast-info';
        toastEl.className = `toast ${typeClass}`;
        const title = (options && options.title) ? `<div class="toast-title">${options.title}</div>` : '';
        const desc = (options && options.description) ? `<div class="toast-description">${options.description}</div>` : '';
        toastEl.innerHTML = `
<div class="toast-content">
${title}
${desc}
</div>
<button class="toast-close" aria-label="Dismiss">&times;</button>
`;
        const closeBtn = toastEl.querySelector('.toast-close');
        closeBtn.addEventListener('click', () => dismissToast(toastEl));
        container.appendChild(toastEl);
        const duration = (options && options.duration) !== undefined ? options.duration : 4000;
        if (duration > 0) {
            setTimeout(() => dismissToast(toastEl), duration);
        }
    }
    if (component === 'tab') {
        const targetId = options && options.id;
        if (!targetId) return;
        const targetContent = document.getElementById(targetId);
        if (!targetContent) return;
        const tabsContainer = targetContent.closest('.tabs');
        if (!tabsContainer) return;
        const allTriggers = tabsContainer.querySelectorAll('.tab-trigger');
        const allContents = tabsContainer.querySelectorAll('.tab-content');
        allContents.forEach(c => c.classList.remove('active'));
        allTriggers.forEach(t => t.classList.remove('active'));
        targetContent.classList.add('active');
        const matchingTrigger = Array.from(allTriggers).find(t =>
            t.getAttribute('data-tab-target') === targetId || t.getAttribute('href') === `#${targetId}`
        );
        if (matchingTrigger) {
            matchingTrigger.classList.add('active');
        }
    }
    if (component === 'command') {
        const action = (options && options.action) || 'toggle';
        let backdrop = document.querySelector('.command-backdrop');
        if (!backdrop) {
            backdrop = createCommandPaletteModal();
        }
        const isOpen = backdrop.classList.contains('open');
        if (action === 'open' || (action === 'toggle' && !isOpen)) {
            backdrop.classList.add('open');
            const input = backdrop.querySelector('.command-input');
            if (input) {
                input.value = '';
                setTimeout(() => input.focus(), 50);
            }
        } else if (action === 'close' || (action === 'toggle' && isOpen)) {
            backdrop.classList.remove('open');
        }
    }
    if (component === 'popover') {
        const id = options && options.id;
        const action = (options && options.action) || 'toggle';
        if (!id) return;
        const popoverEl = document.getElementById(id) || document.querySelector(`[data-popover-id="${id}"]`);
        if (!popoverEl) return;
        const isOpen = popoverEl.classList.contains('open');
        if (action === 'open' || (action === 'toggle' && !isOpen)) {
            popoverEl.classList.add('open');
        } else {
            popoverEl.classList.remove('open');
        }
    }
    if (component === 'drawer') {
        cleanupOrphanedBackdrops();
        const id = options && options.id;
        const action = (options && options.action) || 'toggle';
        if (!id) return;
        const drawerEl = document.getElementById(id);
        if (!drawerEl) return;
        let overlay = document.querySelector(`.drawer-backdrop[data-for="${id}"]`);
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'drawer-backdrop';
            overlay.setAttribute('data-for', id);
            document.body.appendChild(overlay);
            overlay.addEventListener('click', () => {
                bluebird('drawer', { id, action: 'close' });
            });
        }
        const isOpen = drawerEl.classList.contains('open') || drawerEl.classList.contains('active');
        if (action === 'open' || (action === 'toggle' && !isOpen)) {
            drawerEl.classList.add('open');
            overlay.classList.add('open');
            document.body.style.overflow = 'hidden';
        } else if (action === 'close' || (action === 'toggle' && isOpen)) {
            drawerEl.classList.remove('open');
            overlay.classList.remove('open');
            document.body.style.overflow = '';
            setTimeout(() => {
                if (overlay && overlay.parentNode && !drawerEl.classList.contains('open')) {
                    overlay.remove();
                }
            }, 300);
        }
    }
    if (component === 'carousel') {
        const selector = (options && options.selector) || '.carousel';
        const carousels = document.querySelectorAll(selector);
        carousels.forEach(carousel => initSingleCarousel(carousel, options));
    }
    if (component === 'datatable' || component === 'table') {
        const containerId = (options && (options.container || options.id)) || 'datatable';
        return new ResponsiveDataTable(containerId, options);
    }
}
function snackbar(options) {
    bluebird('snackbar', options);
}
function toast(options) {
    bluebird('toast', options);
}
function dismissToast(toastEl) {
    if (!toastEl || toastEl.isDismissing) return;
    toastEl.isDismissing = true;
    toastEl.style.opacity = '0';
    toastEl.style.transform = 'translateY(-10px) scale(0.95)';
    setTimeout(() => {
        if (toastEl.parentNode) {
            toastEl.remove();
        }
    }, 200);
}
function createCommandPaletteModal() {
    const backdrop = document.createElement('div');
    backdrop.className = 'command-backdrop';
    backdrop.innerHTML = `
<div class="command-dialog">
<div class="command-input-wrapper">
<span>🔍</span>
<input type="text" class="command-input" placeholder="Type a command or search documentation..." />
<kbd>ESC</kbd>
</div>
<div class="command-list">
<div class="command-group">
<div class="command-group-title">Navigation</div>
<div class="command-item" data-navigate="#/"><span>Documentation Home</span><kbd>↵</kbd></div>
<div class="command-item" data-navigate="#/buttons"><span>Buttons & Badges</span><kbd>↵</kbd></div>
<div class="command-item" data-navigate="#/forms"><span>Forms & Inputs</span><kbd>↵</kbd></div>
</div>
<div class="command-group">
<div class="command-group-title">Components</div>
<div class="command-item" data-navigate="#/carousel"><span>Touch Carousel</span><kbd>↵</kbd></div>
<div class="command-item" data-navigate="#/aside-drawer"><span>Aside & Drawers</span><kbd>↵</kbd></div>
<div class="command-item" data-navigate="#/animations"><span>CSS Animations</span><kbd>↵</kbd></div>
</div>
</div>
</div>
`;
    document.body.appendChild(backdrop);
    backdrop.addEventListener('click', (e) => {
        if (e.target === backdrop) {
            bluebird('command', { action: 'close' });
        }
    });
    const input = backdrop.querySelector('.command-input');
    input.addEventListener('input', (e) => {
        const query = e.target.value.toLowerCase().trim();
        const items = backdrop.querySelectorAll('.command-item');
        items.forEach(item => {
            const text = item.textContent.toLowerCase();
            item.style.display = text.includes(query) ? 'flex' : 'none';
        });
    });
    backdrop.addEventListener('click', (e) => {
        const item = e.target.closest('.command-item');
        if (item && item.getAttribute('data-navigate')) {
            window.location.hash = item.getAttribute('data-navigate');
            bluebird('command', { action: 'close' });
        }
    });
    return backdrop;
}
function cleanupOrphanedBackdrops() {
    document.querySelectorAll('.drawer-backdrop[data-for]').forEach(backdrop => {
        const targetId = backdrop.getAttribute('data-for');
        if (!document.getElementById(targetId)) {
            backdrop.remove();
        }
    });
}
function initMobileDrawer() {
    const mainEl = document.querySelector('main');
    const aside = mainEl ? mainEl.querySelector(':scope > aside') : null;
    if (!aside) return;
    let overlay = document.querySelector('.bluebird-drawer-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.className = 'bluebird-drawer-overlay';
        document.body.appendChild(overlay);
    }
    let drawer = document.querySelector('.bluebird-drawer');
    if (!drawer) {
        drawer = document.createElement('div');
        drawer.className = 'bluebird-drawer';
        document.body.appendChild(drawer);
    }
    drawer.innerHTML = aside.innerHTML;
    let toggle = document.querySelector('.bluebird-drawer-toggle');
    if (!toggle) {
        toggle = document.createElement('button');
        toggle.className = 'bluebird-drawer-toggle';
        toggle.innerHTML = '☰';
        toggle.setAttribute('aria-label', 'Toggle navigation menu');
        const header = document.querySelector('header');
        if (header) {
            const nav = header.querySelector('nav');
            if (nav) {
                nav.insertBefore(toggle, nav.firstChild);
            } else {
                header.prepend(toggle);
            }
        } else {
            document.body.prepend(toggle);
        }
    }
}
document.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        bluebird('command', { action: 'toggle' });
    }
    if (e.key === 'Escape') {
        const commandBackdrop = document.querySelector('.command-backdrop.open');
        if (commandBackdrop) {
            bluebird('command', { action: 'close' });
        }
    }
});
document.addEventListener('click', function (e) {
    const tabTrigger = e.target.closest('[data-tab-target], .tab-trigger');
    if (tabTrigger) {
        const targetId = tabTrigger.getAttribute('data-tab-target') || (tabTrigger.getAttribute('href') || '').replace('#', '');
        if (targetId) {
            e.preventDefault();
            bluebird('tab', { id: targetId });
        }
    }
    const popoverTrigger = e.target.closest('[data-popover-target]');
    if (popoverTrigger) {
        const popoverId = popoverTrigger.getAttribute('data-popover-target');
        bluebird('popover', { id: popoverId, action: 'toggle' });
    }
    if (!e.target.closest('.popover') && !e.target.closest('[data-popover-target]')) {
        document.querySelectorAll('.popover.open').forEach(p => p.classList.remove('open'));
    }
    const mobileToggle = e.target.closest('.bluebird-drawer-toggle');
    if (mobileToggle) {
        e.preventDefault();
        e.stopPropagation();
        initMobileDrawer();
        const drawer = document.querySelector('.bluebird-drawer');
        const overlay = document.querySelector('.bluebird-drawer-overlay');
        if (drawer && overlay) {
            const isOpen = drawer.classList.contains('open');
            if (isOpen) {
                drawer.classList.remove('open');
                overlay.classList.remove('open');
                document.body.style.overflow = '';
            } else {
                drawer.classList.add('open');
                overlay.classList.add('open');
                document.body.style.overflow = 'hidden';
            }
        }
        return;
    }
    if (e.target.closest('.bluebird-drawer-overlay')) {
        const drawer = document.querySelector('.bluebird-drawer');
        const overlay = document.querySelector('.bluebird-drawer-overlay');
        if (drawer) drawer.classList.remove('open');
        if (overlay) overlay.classList.remove('open');
        document.body.style.overflow = '';
        return;
    }
    if (e.target.closest('.bluebird-drawer a')) {
        const drawer = document.querySelector('.bluebird-drawer');
        const overlay = document.querySelector('.bluebird-drawer-overlay');
        if (drawer) drawer.classList.remove('open');
        if (overlay) overlay.classList.remove('open');
        document.body.style.overflow = '';
    }
    const btn = e.target.closest("button, a[role='button']");
    if (btn && !btn.classList.contains('fab') && !btn.classList.contains('carousel-nav') && !btn.classList.contains('bluebird-drawer-toggle')) {
        const rect = btn.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        const x = e.clientX - rect.left - size / 2;
        const y = e.clientY - rect.top - size / 2;
        const ripple = document.createElement('span');
        ripple.className = 'ripple';
        ripple.style.width = ripple.style.height = size + 'px';
        ripple.style.left = x + 'px';
        ripple.style.top = y + 'px';
        btn.appendChild(ripple);
        ripple.addEventListener('animationend', () => ripple.remove());
    }
    const drawerTrigger = e.target.closest('[data-drawer-target]');
    if (drawerTrigger) {
        const id = drawerTrigger.getAttribute('data-drawer-target');
        bluebird('drawer', { id, action: 'toggle' });
    }
    const drawerClose = e.target.closest('[data-drawer-close]');
    if (drawerClose) {
        const drawerEl = drawerClose.closest('.drawer');
        if (drawerEl && drawerEl.id) {
            bluebird('drawer', { id: drawerEl.id, action: 'close' });
        }
    }
});
function initSingleCarousel(carousel, opts = {}) {
    if (carousel._bb_initialized) return;
    carousel._bb_initialized = true;
    const track = carousel.querySelector('.carousel-track');
    if (!track) return;
    const items = Array.from(track.querySelectorAll('.carousel-item, .carousel-card'));
    if (items.length === 0) return;
    const prevBtn = carousel.querySelector('.carousel-prev');
    const nextBtn = carousel.querySelector('.carousel-next');
    let indicatorsContainer = carousel.querySelector('.carousel-indicators');
    let currentSlideIndex = 0;
    if (indicatorsContainer && indicatorsContainer.children.length === 0) {
        items.forEach((_, idx) => {
            const dot = document.createElement('button');
            dot.className = `carousel-dot ${idx === 0 ? 'active' : ''}`;
            dot.setAttribute('aria-label', `Go to slide ${idx + 1}`);
            dot.addEventListener('click', (e) => {
                e.preventDefault();
                scrollToSlide(idx);
            });
            indicatorsContainer.appendChild(dot);
        });
    }
    function scrollToSlide(index) {
        if (index < 0) index = 0;
        if (index >= items.length) index = items.length - 1;
        currentSlideIndex = index;
        const targetItem = items[index];
        if (targetItem) {
            track.scrollTo({
                left: targetItem.offsetLeft - track.offsetLeft,
                behavior: 'smooth'
            });
            updateIndicators(index);
        }
    }
    function updateIndicators(activeIndex) {
        if (!indicatorsContainer) return;
        const dots = Array.from(indicatorsContainer.children);
        dots.forEach((dot, idx) => {
            dot.classList.toggle('active', idx === activeIndex);
        });
    }
    let scrollTimeout;
    track.addEventListener('scroll', () => {
        clearTimeout(scrollTimeout);
        scrollTimeout = setTimeout(() => {
            const trackLeft = track.scrollLeft;
            let closestIndex = 0;
            let minDistance = Infinity;
            items.forEach((item, idx) => {
                const distance = Math.abs(item.offsetLeft - track.offsetLeft - trackLeft);
                if (distance < minDistance) {
                    minDistance = distance;
                    closestIndex = idx;
                }
            });
            currentSlideIndex = closestIndex;
            updateIndicators(closestIndex);
        }, 40);
    });
    if (prevBtn) {
        prevBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            scrollToSlide(currentSlideIndex - 1);
        });
    }
    if (nextBtn) {
        nextBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            scrollToSlide(currentSlideIndex + 1);
        });
    }
    let startX = 0;
    let isDragging = false;
    track.addEventListener('touchstart', (e) => {
        startX = e.touches[0].clientX;
        isDragging = true;
    }, { passive: true });
    track.addEventListener('touchend', (e) => {
        if (!isDragging) return;
        isDragging = false;
        const endX = e.changedTouches[0].clientX;
        const diffX = startX - endX;
        if (Math.abs(diffX) > 35) {
            if (diffX > 0) {
                scrollToSlide(currentSlideIndex + 1);
            } else {
                scrollToSlide(currentSlideIndex - 1);
            }
        }
    });
    track.addEventListener('mousedown', (e) => {
        startX = e.clientX;
        isDragging = true;
        track.style.cursor = 'grabbing';
    });
    track.addEventListener('mouseleave', () => {
        isDragging = false;
        track.style.cursor = 'grab';
    });
    track.addEventListener('mouseup', (e) => {
        if (!isDragging) return;
        isDragging = false;
        track.style.cursor = 'grab';
        const endX = e.clientX;
        const diffX = startX - endX;
        if (Math.abs(diffX) > 35) {
            if (diffX > 0) {
                scrollToSlide(currentSlideIndex + 1);
            } else {
                scrollToSlide(currentSlideIndex - 1);
            }
        }
    });
    const isAutoplay = (opts && opts.autoplay) || carousel.getAttribute('data-autoplay') === 'true';
    const intervalTime = parseInt((opts && opts.interval) || carousel.getAttribute('data-interval') || 3500, 10);
    if (isAutoplay) {
        let autoInterval = setInterval(() => {
            const nextIdx = (currentSlideIndex + 1) % items.length;
            scrollToSlide(nextIdx);
        }, intervalTime);
        carousel.addEventListener('mouseenter', () => clearInterval(autoInterval));
        carousel.addEventListener('mouseleave', () => {
            autoInterval = setInterval(() => {
                const nextIdx = (currentSlideIndex + 1) % items.length;
                scrollToSlide(nextIdx);
            }, intervalTime);
        });
    }
}
(function setupDeclarativeListeners() {
    document.addEventListener('click', (e) => {
        const copyTrigger = e.target.closest('[data-copy]');
        if (copyTrigger) {
            e.preventDefault();
            const targetAttr = copyTrigger.getAttribute('data-copy');
            let textToCopy = targetAttr;
            if (targetAttr && (targetAttr.startsWith('#') || targetAttr.startsWith('.'))) {
                const targetEl = document.querySelector(targetAttr);
                if (targetEl) {
                    textToCopy = targetEl.value !== undefined ? targetEl.value : (targetEl.innerText || targetEl.textContent);
                }
            }
            if (textToCopy) {
                navigator.clipboard.writeText(textToCopy.trim()).then(() => {
                    copyTrigger.classList.add('copied');
                    if (typeof bluebird === 'function') {
                        bluebird('toast', {
                            title: 'Copied to clipboard',
                            description: textToCopy.length > 50 ? textToCopy.substring(0, 50) + '...' : textToCopy,
                            type: 'success',
                            duration: 2500
                        });
                    }
                    setTimeout(() => copyTrigger.classList.remove('copied'), 2000);
                });
            }
            return;
        }
        const confirmTrigger = e.target.closest('[data-confirm]');
        if (confirmTrigger) {
            const msg = confirmTrigger.getAttribute('data-confirm') || 'Are you sure?';
            if (!window.confirm(msg)) {
                e.preventDefault();
                e.stopImmediatePropagation();
                return;
            }
        }
        const scrollTrigger = e.target.closest('[data-scroll-to]');
        if (scrollTrigger) {
            e.preventDefault();
            const targetId = scrollTrigger.getAttribute('data-scroll-to');
            const targetEl = document.querySelector(targetId);
            if (targetEl) {
                targetEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
            return;
        }
        const passToggle = e.target.closest('[data-password-toggle]');
        if (passToggle) {
            e.preventDefault();
            const targetSelector = passToggle.getAttribute('data-password-toggle');
            const input = targetSelector
                ? document.querySelector(targetSelector)
                : (passToggle.closest('.input-group, .form-input-group, div')?.querySelector('input') || passToggle.previousElementSibling);
            if (input && (input.type === 'password' || input.type === 'text')) {
                const isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';
                passToggle.classList.toggle('showing', isPassword);
                passToggle.setAttribute('aria-pressed', isPassword ? 'true' : 'false');
            }
            return;
        }
        const stepUp = e.target.closest('[data-step-up]');
        if (stepUp) {
            e.preventDefault();
            const targetInput = document.querySelector(stepUp.getAttribute('data-step-up')) ||
                stepUp.closest('.stepper')?.querySelector('input[type="number"]');
            if (targetInput && typeof targetInput.stepUp === 'function') {
                targetInput.stepUp();
                targetInput.dispatchEvent(new Event('input', { bubbles: true }));
                targetInput.dispatchEvent(new Event('change', { bubbles: true }));
            }
            return;
        }
        const stepDown = e.target.closest('[data-step-down]');
        if (stepDown) {
            e.preventDefault();
            const targetInput = document.querySelector(stepDown.getAttribute('data-step-down')) ||
                stepDown.closest('.stepper')?.querySelector('input[type="number"]');
            if (targetInput && typeof targetInput.stepDown === 'function') {
                targetInput.stepDown();
                targetInput.dispatchEvent(new Event('input', { bubbles: true }));
                targetInput.dispatchEvent(new Event('change', { bubbles: true }));
            }
            return;
        }
        const selectItem = e.target.closest('[data-select-value]');
        if (selectItem) {
            const val = selectItem.getAttribute('data-select-value');
            const targetSelector = selectItem.getAttribute('data-select-target') ||
                selectItem.closest('[data-select-container]')?.getAttribute('data-select-target');
            if (targetSelector) {
                const targetEl = document.querySelector(targetSelector);
                if (targetEl) {
                    if (targetEl.tagName === 'INPUT' || targetEl.tagName === 'SELECT') {
                        targetEl.value = val;
                        targetEl.dispatchEvent(new Event('input', { bubbles: true }));
                        targetEl.dispatchEvent(new Event('change', { bubbles: true }));
                    } else {
                        targetEl.textContent = selectItem.textContent.trim();
                    }
                }
            }
            const parentDropdown = selectItem.closest('.dropdown-content, .popover-content');
            if (parentDropdown) {
                parentDropdown.classList.remove('open');
            }
        }
        const modalTrigger = e.target.closest('[data-toggle="modal"], [data-modal-target], [data-dialog-target]');
        if (modalTrigger) {
            e.preventDefault();
            const targetSelector = modalTrigger.getAttribute('data-modal-target') ||
                modalTrigger.getAttribute('data-dialog-target') ||
                modalTrigger.getAttribute('data-target') ||
                modalTrigger.getAttribute('href');
            if (targetSelector) {
                const dialog = document.querySelector(targetSelector);
                if (dialog && typeof dialog.showModal === 'function') {
                    dialog.showModal();
                }
            }
            return;
        }
        const dismissTrigger = e.target.closest('[data-dismiss="modal"], [data-close-dialog], [data-close-modal]');
        if (dismissTrigger) {
            e.preventDefault();
            const dialog = dismissTrigger.closest('dialog') ||
                document.querySelector(dismissTrigger.getAttribute('data-target') || '');
            if (dialog && typeof dialog.close === 'function') {
                dialog.close();
            }
            return;
        }
        const themeTrigger = e.target.closest('[data-toggle="theme"]');
        if (themeTrigger) {
            e.preventDefault();
            const html = document.documentElement;
            const current = html.getAttribute('data-theme') || 'light';
            const next = current === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', next);
            try {
                localStorage.setItem('bluebird-theme', next);
            } catch (err) { }
            return;
        }
        const toastTrigger = e.target.closest('[data-toast]');
        if (toastTrigger) {
            e.preventDefault();
            const desc = toastTrigger.getAttribute('data-toast') || '';
            const title = toastTrigger.getAttribute('data-toast-title') || '';
            const type = toastTrigger.getAttribute('data-toast-type') || 'info';
            if (typeof bluebird === 'function') {
                bluebird('toast', { title, description: desc, type });
            }
            return;
        }
        const snackbarTrigger = e.target.closest('[data-snackbar]');
        if (snackbarTrigger) {
            e.preventDefault();
            const message = snackbarTrigger.getAttribute('data-snackbar') || '';
            const type = snackbarTrigger.getAttribute('data-snackbar-type') || 'info';
            if (typeof bluebird === 'function') {
                bluebird('snackbar', { message, type });
            }
            return;
        }
        if (!e.target.closest('.dropdown') && !e.target.closest('.popover')) {
            document.querySelectorAll('.dropdown-content.open, .popover-content.open').forEach(el => {
                el.classList.remove('open');
            });
        }
    });
    document.addEventListener('input', (e) => {
        const filterInput = e.target.closest('[data-filter-target]');
        if (filterInput) {
            const targetSelector = filterInput.getAttribute('data-filter-target');
            const targetContainer = document.querySelector(targetSelector);
            if (targetContainer) {
                const query = filterInput.value.toLowerCase().trim();
                const items = targetContainer.querySelectorAll('[data-filter-item], li, tr, .card, .dropdown-item, .item');
                let visibleCount = 0;
                items.forEach(item => {
                    const text = item.textContent.toLowerCase();
                    const matches = text.includes(query);
                    item.style.display = matches ? '' : 'none';
                    if (matches) visibleCount++;
                });
                const emptyMsg = targetContainer.querySelector('.no-filter-results');
                if (emptyMsg) {
                    emptyMsg.style.display = visibleCount === 0 ? 'block' : 'none';
                }
            }
        }
        if (e.target.matches('textarea[data-auto-resize]')) {
            const textarea = e.target;
            textarea.style.height = 'auto';
            textarea.style.height = (textarea.scrollHeight + 2) + 'px';
        }
    });
    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            if (typeof bluebird === 'function') {
                bluebird('command', { action: 'toggle' });
            }
        } else if (e.key === 'Escape') {
            const openCommand = document.querySelector('.command-backdrop.open');
            if (openCommand && typeof bluebird === 'function') {
                bluebird('command', { action: 'close' });
            }
        }
    });
})();
(function () {
    function init() {
        cleanupOrphanedBackdrops();
        initMobileDrawer();
        document.querySelectorAll('.carousel').forEach(c => initSingleCarousel(c));
        document.querySelectorAll('textarea[data-auto-resize]').forEach(t => {
            t.style.height = 'auto';
            t.style.height = (t.scrollHeight + 2) + 'px';
        });
        try {
            const savedTheme = localStorage.getItem('bluebird-theme');
            if (savedTheme) {
                document.documentElement.setAttribute('data-theme', savedTheme);
            }
        } catch (e) { }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => setTimeout(init, 100));
    } else {
        setTimeout(init, 100);
    }
})();
async function Http(
    url = "/",
    method = "GET",
    body = false,
    bodyForm = false,
    headers = {},
) {
    const csrfEl = document.getElementById("csrf");
    const csrfToken = csrfEl ? csrfEl.value : null;
    const mergedHeaders = { ...headers };
    if (csrfToken) {
        mergedHeaders["X-CSRF-Token"] = csrfToken;
    }
    const options = { method: method, headers: mergedHeaders, credentials: "include" };
    if (body) {
        const payload = csrfToken ? { ...body, csrf: csrfToken } : body;
        options["body"] = JSON.stringify(payload);
        if (!mergedHeaders["Content-Type"]) {
            mergedHeaders["Content-Type"] = "application/json";
        }
    }
    if (bodyForm) {
        if (csrfToken && bodyForm instanceof FormData) {
            bodyForm.append("csrf", csrfToken);
        }
        options["body"] = bodyForm;
    }
    const response = await fetch(url, options);
    if (!response.ok) {
        let errorData;
        try {
            errorData = await response.json();
        } catch {
            errorData = { message: `HTTP Error ${response.status}: ${response.statusText}` };
        }
        throw new Error(errorData.message || errorData.msg || errorData.mensaje || "Fetch request failed");
    }
    return await response.json();
}
function getUrlParameter(name) {
    return new URLSearchParams(window.location.search).get(name);
}
function lang(l = "es") {
    const docLang = document.documentElement.lang || "es";
    return docLang === l || docLang.startsWith(l);
}
class ResponsiveDataTable {
    constructor(containerId, options = {}) {
        this.container = typeof containerId === "string" ? document.getElementById(containerId) : containerId;
        if (!this.container) return;
        this.defaults = {
            data: [],
            columns: [],
            rowsPerPage: 10,
            search: true,
            pagination: true,
            headerTitles: {},
            summaryFields: ["id"],
            edit: false,
            delete: false,
            breakpoint: 768,
        };
        this.options = { ...this.defaults, ...options };
        this.currentPage = 1;
        this.filteredData = [...this.options.data];
        this.isMobile = window.innerWidth < this.options.breakpoint;
        this.init();
        window.addEventListener("resize", () => this.handleResize());
    }
    init() {
        this.renderContainer();
        this.updateTable();
        if (this.options.search) this.setupSearch();
    }
    handleResize() {
        const wasMobile = this.isMobile;
        this.isMobile = window.innerWidth < this.options.breakpoint;
        if (wasMobile !== this.isMobile) this.updateTable();
    }
    renderContainer() {
        this.container.innerHTML = `
<section class="w-full">
${this.options.search
                ? `<div class="mb-4 flex items-center justify-between"><input type="search" class="datatable-search-input outline" placeholder="${lang() ? "Buscar..." : "Search..."}" aria-label="Search"/></div>`
                : ""
            }
<div class="overflow-x-auto">
<table class="datatable-table hidden"></table>
<div class="datatable-mobile"></div>
</div>
${this.options.pagination ? `<nav class="datatable-pagination mt-4 flex items-center justify-center gap-1" aria-label="Pagination"></nav>` : ""}
</section>`;
    }
    renderTable() {
        const table = this.container.querySelector(".datatable-table");
        const mobileView = this.container.querySelector(".datatable-mobile");
        if (!table || !mobileView) return;
        if (this.isMobile) {
            table.classList.add("hidden");
            mobileView.classList.remove("hidden");
            this.renderMobileView();
        } else {
            table.classList.remove("hidden");
            mobileView.classList.add("hidden");
            this.renderDesktopTable();
        }
    }
    renderDesktopTable() {
        const table = this.container.querySelector(".datatable-table");
        table.innerHTML = `
<thead>
<tr class="datatable-header"></tr>
</thead>
<tbody class="datatable-body"></tbody>`;
        const headerRow = table.querySelector("thead tr");
        this.options.columns.forEach((column) => {
            const th = document.createElement("th");
            th.scope = "col";
            th.textContent =
                this.options.headerTitles[column.key] || column.title || column.key;
            headerRow.appendChild(th);
        });
        if (this.options.edit || this.options.delete) {
            const th = document.createElement("th");
            th.scope = "col";
            th.textContent = lang() ? "Acciones" : "Actions";
            headerRow.appendChild(th);
        }
        const startIndex = (this.currentPage - 1) * this.options.rowsPerPage;
        const endIndex = startIndex + this.options.rowsPerPage;
        const paginatedData = this.filteredData.slice(startIndex, endIndex);
        const tbody = table.querySelector("tbody");
        paginatedData.forEach((item) => {
            const row = document.createElement("tr");
            this.options.columns.forEach((column) => {
                const td = document.createElement("td");
                const value = item[column.key];
                if (
                    value &&
                    typeof value === "string" &&
                    /<[a-z][\s\S]*>/i.test(value)
                ) {
                    td.innerHTML = value;
                } else {
                    td.textContent = value !== undefined && value !== null ? value : "-";
                }
                row.appendChild(td);
            });
            if (this.options.edit || this.options.delete) {
                const td = document.createElement("td");
                const actionsDiv = document.createElement("div");
                actionsDiv.className = "flex items-center gap-2";
                if (this.options.edit) {
                    const btn = document.createElement("button");
                    btn.className = "outline";
                    btn.textContent = lang() ? "Editar" : "Edit";
                    btn.onclick = (e) => this.handleAction("edit", e, item);
                    actionsDiv.appendChild(btn);
                }
                if (this.options.delete) {
                    const btn = document.createElement("button");
                    btn.className = "destructive";
                    btn.textContent = lang() ? "Eliminar" : "Delete";
                    btn.onclick = (e) => this.handleAction("delete", e, item);
                    actionsDiv.appendChild(btn);
                }
                td.appendChild(actionsDiv);
                row.appendChild(td);
            }
            tbody.appendChild(row);
        });
    }
    renderMobileView() {
        const mobileView = this.container.querySelector(".datatable-mobile");
        mobileView.innerHTML = "";
        const startIndex = (this.currentPage - 1) * this.options.rowsPerPage;
        const endIndex = startIndex + this.options.rowsPerPage;
        const paginatedData = this.filteredData.slice(startIndex, endIndex);
        paginatedData.forEach((item) => {
            const card = document.createElement("article");
            card.className = "card mb-4";
            const summary = document.createElement("h3");
            summary.className =
                "flex items-center justify-between font-bold mb-2 pb-2";
            this.options.summaryFields.forEach((fieldKey) => {
                const value = item[fieldKey];
                summary.innerHTML += `<span>${value !== undefined && value !== null ? value : "-"}</span>`;
            });
            card.appendChild(summary);
            const details = document.createElement("dl");
            details.className =
                "grid cols-1 gap-2 mb-2 pb-2";
            this.options.columns.forEach((column) => {
                if (this.options.summaryFields.includes(column.key)) return;
                const dt = document.createElement("dt");
                dt.className =
                    "font-bold text-muted";
                dt.textContent =
                    this.options.headerTitles[column.key] || column.title || column.key;
                const dd = document.createElement("dd");
                dd.className =
                    "text-left";
                const cellValue = item[column.key];
                if (
                    cellValue &&
                    typeof cellValue === "string" &&
                    /<[a-z][\s\S]*>/i.test(cellValue)
                ) {
                    dd.innerHTML = cellValue;
                } else {
                    dd.textContent = cellValue !== undefined && cellValue !== null ? cellValue : "-";
                }
                details.appendChild(dt);
                details.appendChild(dd);
            });
            card.appendChild(details);
            if (this.options.edit || this.options.delete) {
                const actions = document.createElement("div");
                actions.className = "flex items-center gap-2 justify-end";
                if (this.options.edit) {
                    const btn = document.createElement("button");
                    btn.className = "outline";
                    btn.textContent = lang() ? "Editar" : "Edit";
                    btn.onclick = (e) => this.handleAction("edit", e, item);
                    actions.appendChild(btn);
                }
                if (this.options.delete) {
                    const btn = document.createElement("button");
                    btn.className = "destructive";
                    btn.textContent = lang() ? "Eliminar" : "Delete";
                    btn.onclick = (e) => this.handleAction("delete", e, item);
                    actions.appendChild(btn);
                }
                card.appendChild(actions);
            }
            mobileView.appendChild(card);
        });
    }
    renderPagination() {
        const pagination = this.container.querySelector(".datatable-pagination");
        if (!pagination || !this.options.pagination) return;
        pagination.innerHTML = "";
        const pageCount = Math.ceil(
            this.filteredData.length / this.options.rowsPerPage,
        );
        if (pageCount <= 1) return;
        const baseClass = "px-3 py-2 outline";
        const activeClass = "px-3 py-2";
        const prevButton = document.createElement("button");
        prevButton.textContent = "«";
        prevButton.className = baseClass;
        prevButton.disabled = this.currentPage === 1;
        prevButton.onclick = () => this.changePage(this.currentPage - 1);
        pagination.appendChild(prevButton);
        const maxVisible = 5;
        let start = Math.max(1, this.currentPage - Math.floor(maxVisible / 2));
        let end = start + maxVisible - 1;
        if (end > pageCount) {
            end = pageCount;
            start = Math.max(1, end - maxVisible + 1);
        }
        if (start > 1) {
            const firstButton = document.createElement("button");
            firstButton.className = baseClass;
            firstButton.textContent = "1";
            firstButton.onclick = () => this.changePage(1);
            pagination.appendChild(firstButton);
            if (start > 2) pagination.appendChild(this.createEllipsis());
        }
        for (let i = start; i <= end; i++) {
            const button = document.createElement("button");
            button.className =
                i === this.currentPage ? activeClass : baseClass;
            button.textContent = i;
            button.onclick = () => this.changePage(i);
            pagination.appendChild(button);
        }
        if (end < pageCount) {
            if (end < pageCount - 1) pagination.appendChild(this.createEllipsis());
            const lastButton = document.createElement("button");
            lastButton.className = baseClass;
            lastButton.textContent = pageCount;
            lastButton.onclick = () => this.changePage(pageCount);
            pagination.appendChild(lastButton);
        }
        const nextButton = document.createElement("button");
        nextButton.className = baseClass;
        nextButton.textContent = "»";
        nextButton.disabled = this.currentPage === pageCount;
        nextButton.onclick = () => this.changePage(this.currentPage + 1);
        pagination.appendChild(nextButton);
    }
    createEllipsis() {
        const span = document.createElement("span");
        span.className = "px-2 text-muted";
        span.textContent = "...";
        return span;
    }
    changePage(page) {
        this.currentPage = page;
        this.updateTable();
    }
    handleAction(type, event, item) {
        if (!this.options[type]) return;
        const callback =
            typeof this.options[type] === "function"
                ? this.options[type]
                : window[this.options[type]];
        if (typeof callback === "function") callback(event, item.id || item);
    }
    setupSearch() {
        const searchInput = this.container.querySelector(".datatable-search-input");
        if (!searchInput) return;
        searchInput.addEventListener("input", (e) => {
            const term = e.target.value.toLowerCase().trim();
            this.filteredData = this.options.data.filter((item) =>
                this.options.columns.some((column) =>
                    String(item[column.key] || "")
                        .toLowerCase()
                        .includes(term),
                ),
            );
            this.currentPage = 1;
            this.updateTable();
        });
    }
    updateTable() {
        this.renderTable();
        if (this.options.pagination) this.renderPagination();
    }
    updateData(newData) {
        this.options.data = newData;
        this.filteredData = [...newData];
        this.currentPage = 1;
        this.updateTable();
    }
    updateColumns(newColumns) {
        this.options.columns = newColumns;
        this.updateTable();
    }
}
if (typeof window !== "undefined") {
    window.ResponsiveDataTable = ResponsiveDataTable;
    window.Http = Http;
    window.getUrlParameter = getUrlParameter;
    window.snackbar = snackbar;
    window.toast = toast;
    window.bluebird = bluebird; window.dismissToast = dismissToast; window.initMobileDrawer = initMobileDrawer; window.initSingleCarousel = initSingleCarousel; window.lang = lang;
}