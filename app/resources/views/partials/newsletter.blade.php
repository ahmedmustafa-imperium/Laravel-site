<section class="newsletter-section">
    <div class="container newsletter-card">
        <div><span class="eyebrow">Stay in the loop</span><h2>Fresh offers, practical ideas.</h2><p>Get wholesale offers, new arrivals and packaging inspiration in your inbox.</p></div>
        <form action="{{ route('newsletter.store') }}" method="post">@csrf<label class="sr-only" for="newsletter-email">Email address</label><input id="newsletter-email" type="email" name="email" placeholder="Your email address" required><button class="button button--lime" type="submit">Subscribe</button></form>
    </div>
</section>
