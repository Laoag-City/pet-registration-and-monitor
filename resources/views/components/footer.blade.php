<footer class="site-footer" id="about">
    <div class="container footer-shell">
        <div>
            <div class="brand brand--footer">
                <span class="brand-mark" aria-hidden="true">
                    <img src="{{ asset('images/city vet.jpg') }}" alt="">
                </span>
                {{-- d="M9.5 7.2c1.8 0 3.2-1.4 3.2-3.2S11.3 0 9.5 0 6.3 1.4 6.3 3.2s1.5 3.2 3.2 3.2Zm13 0c1.8 0 3.2-1.4 3.2-3.2S24.3 0 22.5 0s-3.2 1.4-3.2 3.2 1.5 3.2 3.2 3.2ZM4.6 16.1c1.8 0 3.2-1.4 3.2-3.2s-1.4-3.2-3.2-3.2S1.4 11.1 1.4 13s1.5 3.1 3.2 3.1Zm22.8 0c1.8 0 3.2-1.4 3.2-3.2s-1.4-3.2-3.2-3.2-3.2 1.4-3.2 3.2 1.4 3.2 3.2 3.2ZM16 11.6c2.3 0 4.1 1.9 4.1 4.1v6.6c0 2.3-1.8 4.1-4.1 4.1s-4.1-1.8-4.1-4.1v-6.6c0-2.2 1.8-4.1 4.1-4.1Zm2.1-8.7c1.8 0 3.2-1.4 3.2-3.2S19.9 0 18.1 0s-3.2 1.4-3.2 3.2 1.4 3.2 3.2 3.2Zm-10.6 6.8c1.6 0 3-1.3 3-3s-1.3-3-3-3-3 1.3-3 3 1.4 3 3 3Zm18.9 0c1.6 0 3-1.3 3-3s-1.3-3-3-3-3 1.3-3 3 1.4 3 3 3Z" fill="currentColor"/>
                --}}
                <span class="brand-text">{{ config('app.organization') }}</span>
            </div>
            <p class="footer-tagline">QR-Based Pet Registration and Medical Record System</p>
            <p class="footer-tagline">{{ config('app.government') }}</p>
        </div>

        <div class="footer-links">
            <a href="/">Home</a>
            <a href="{{ route('features') }}">Features</a>
            <a href="{{ route('how-it-works') }}">How It Works</a>
            <a href="{{ route('login') }}">Log In</a>
        </div>
    </div>
    <div class="container footer-meta">
        <p>© 2026 {{ config('app.organization') }}, {{ config('app.government') }}. All rights reserved.</p>
    </div>
</footer>
