@extends('website.layout')

@section('content')
<script>
window.__productVariantCache = @json($variantLiveCache ?? new \stdClass());

window.productLive = function () {
    return {
        cache: {},
        _req: 0,
        _prefetching: {},

        init() {
            const seeded = window.__productVariantCache || {};
            Object.keys(seeded).forEach((url) => {
                this.cache[this.cacheKey(url)] = seeded[url];
            });

            // Keep the first paint HTML as the current variant cache entry.
            const box = this.$root.querySelector('[data-product-live]');
            const here = this.cacheKey(window.location.href);
            if (box && !this.cache[here]) {
                this.cache[here] = {
                    html: box.innerHTML,
                    url: window.location.pathname + window.location.search,
                    title: document.title,
                };
            }

            // Warm images so color swaps don't flash empty media.
            Object.values(this.cache).forEach((payload) => this.prefetchImages(payload));

            this.prefetchVisible();
        },

        prefetchImages(payload) {
            if (!payload || !payload.html || typeof document === 'undefined') return;
            try {
                const tmp = document.createElement('div');
                tmp.innerHTML = payload.html;
                tmp.querySelectorAll('img[src]').forEach((img) => {
                    const src = img.getAttribute('src');
                    if (!src) return;
                    const warm = new Image();
                    warm.decoding = 'async';
                    warm.src = src;
                });
            } catch (e) {}
        },

        cacheKey(url) {
            try {
                const u = new URL(url, window.location.origin);
                u.searchParams.delete('ajax');
                return u.origin + u.pathname + u.search;
            } catch (e) {
                return String(url || '');
            }
        },

        onVariantClick(event) {
            const link = event.target.closest('a[data-product-variant]');
            if (!link || !this.$root.contains(link)) return;

            event.preventDefault();
            event.stopPropagation();

            if (link.classList.contains('is-active')) return;

            const url = link.getAttribute('href');
            if (!url || url === '#') return;

            this.loadVariant(url);
        },

        applyPayload(data, { push = true } = {}) {
            if (!data || !data.html) return;

            const box = this.$root.querySelector('[data-product-live]');
            if (!box) return;

            if (window.Alpine && typeof Alpine.destroyTree === 'function') {
                Alpine.destroyTree(box);
            }
            box.innerHTML = data.html;
            if (window.Alpine && typeof Alpine.initTree === 'function') {
                Alpine.initTree(box);
            }

            if (data.title) {
                document.title = data.title;
            }

            if (push && data.url) {
                history.pushState({ productLive: true }, '', data.url);
            }

            // Warm any newly revealed option links (e.g. storage after color).
            this.$nextTick(() => this.prefetchVisible());
        },

        async fetchVariant(url) {
            const key = this.cacheKey(url);
            if (this.cache[key]) return this.cache[key];
            if (this._prefetching[key]) return this._prefetching[key];

            const fetchUrl = url.includes('?') ? `${url}&ajax=1` : `${url}?ajax=1`;
            const promise = fetch(fetchUrl, {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            }).then(async (res) => {
                if (!res.ok) throw new Error('Variant load failed');
                const data = await res.json();
                if (data && data.html) {
                    this.cache[this.cacheKey(data.url || url)] = data;
                    this.cache[key] = data;
                    this.prefetchImages(data);
                }
                return data;
            }).finally(() => {
                delete this._prefetching[key];
            });

            this._prefetching[key] = promise;
            return promise;
        },

        prefetchVisible() {
            const links = this.$root.querySelectorAll('a[data-product-variant][href]');
            links.forEach((link) => {
                const url = link.getAttribute('href');
                if (!url || url === '#' || link.classList.contains('is-active')) return;
                const key = this.cacheKey(url);
                if (this.cache[key] || this._prefetching[key]) return;
                // Fire-and-forget warm cache
                this.fetchVariant(url).catch(() => {});
            });
        },

        async loadVariant(url, { push = true } = {}) {
            if (!url) return;
            const req = ++this._req;
            const key = this.cacheKey(url);

            try {
                let data = this.cache[key];
                if (!data) {
                    data = await this.fetchVariant(url);
                }
                if (req !== this._req || !data) return;
                this.applyPayload(data, { push });
            } catch (e) {
                if (req === this._req && typeof console !== 'undefined') {
                    console.warn('Variant swap failed', e);
                }
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
     x-init="init()"
     @click.capture="onVariantClick($event)"
     @popstate.window="onPopState()">
    <div data-product-live>
        @include('website.partials.product-live')
    </div>
</div>
@endsection
