@extends('website.layout')

@section('content')
<script>
window.productLive = function () {
    return {
        loading: false,
        _req: 0,

        onVariantClick(event) {
            const link = event.target.closest('a[data-product-variant]');
            if (!link || !this.$root.contains(link)) return;

            event.preventDefault();
            event.stopPropagation();

            if (link.classList.contains('is-active') || this.loading) return;

            const url = link.getAttribute('href');
            if (!url || url === '#') return;

            this.loadVariant(url);
        },

        async loadVariant(url, { push = true } = {}) {
            if (!url) return;
            const req = ++this._req;
            this.loading = true;

            try {
                const fetchUrl = url.includes('?')
                    ? `${url}&ajax=1`
                    : `${url}?ajax=1`;

                const res = await fetch(fetchUrl, {
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!res.ok) throw new Error('Variant load failed');

                const data = await res.json();
                if (req !== this._req) return;

                const box = this.$root.querySelector('[data-product-live]');
                if (box && data.html) {
                    if (window.Alpine && typeof Alpine.destroyTree === 'function') {
                        Alpine.destroyTree(box);
                    }
                    box.innerHTML = data.html;
                    if (window.Alpine && typeof Alpine.initTree === 'function') {
                        Alpine.initTree(box);
                    }
                }

                if (data.title) {
                    document.title = data.title;
                }

                if (push && data.url) {
                    history.pushState({ productLive: true }, '', data.url);
                }
            } catch (e) {
                // Stay on the page — never full-reload for a color/type switch.
                if (req === this._req && typeof console !== 'undefined') {
                    console.warn('Variant swap failed', e);
                }
            } finally {
                if (req === this._req) this.loading = false;
            }
        },

        onPopState() {
            if (!/\/product\//.test(window.location.pathname)) return;
            this.loadVariant(window.location.href, { push: false });
        },
    };
};

// Capture early so the browser never navigates away on color/type clicks,
// even if Alpine has not finished starting yet.
document.addEventListener('click', function (event) {
    const link = event.target.closest('a[data-product-variant]');
    if (!link) return;

    const root = link.closest('[data-product-live-root]');
    if (!root) return;

    event.preventDefault();
    event.stopPropagation();

    if (link.classList.contains('is-active')) return;

    const url = link.getAttribute('href');
    if (!url || url === '#') return;

    const invoke = function () {
        let api = null;
        if (window.Alpine && typeof Alpine.$data === 'function') {
            try { api = Alpine.$data(root); } catch (e) {}
        }
        if (!api && root._x_dataStack) {
            api = root._x_dataStack[0];
        }
        if (api && typeof api.loadVariant === 'function') {
            api.loadVariant(url);
            return true;
        }
        return false;
    };

    if (invoke()) return;

    let tries = 0;
    const timer = setInterval(function () {
        tries += 1;
        if (invoke() || tries > 50) clearInterval(timer);
    }, 20);
}, true);
</script>

<div class="max-w-7xl mx-auto px-4 py-3 sm:py-4"
     data-product-live-root
     x-data="productLive()"
     :class="{ 'pd-live-loading': loading }"
     @click.capture="onVariantClick($event)"
     @popstate.window="onPopState()">
    <div data-product-live>
        @include('website.partials.product-live')
    </div>
</div>
@endsection
