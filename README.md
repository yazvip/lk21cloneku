# MovieTube - Advanced Streaming Platform

Platform streaming film dan series dengan **UI/UX premium**, **SEO-optimized**, dan **performance tinggi** untuk desktop & mobile.

## ✨ Fitur Unggulan

### 🎬 Core Features
- **PWA Ready** - Install sebagai aplikasi native Android/iOS
- **Smart Search** - Real-time autocomplete suggestions
- **Watch History** - Auto-track progress tontonan
- **Watchlist** - Simpan film favorit dengan 1-tap
- **Resume Watching** - Lanjutkan dari terakhir tonton
- **Infinite Scroll** - Seamless content loading
- **Multi-Language** - Support subtitle Indonesia

### 🎨 UI/UX Premium
- **60fps Animations** - GPU-accelerated smooth transitions
- **Parallax Effects** - Dynamic hero section
- **3D Card Tilt** - Interactive hover effects (desktop)
- **Glassmorphism** - Modern glass effects
- **Custom Cursor** - Enhanced desktop interaction
- **Pull to Refresh** - Native mobile gesture
- **Scroll Animations** - Fade/slide/scale on scroll
- **Micro-interactions** - Detailed button feedbacks
- **Ambient Lighting** - Dynamic color extraction
- **Toast Notifications** - Smooth slide-in alerts

### ⚡ Performance
- **Core Web Vitals Optimized**
  - LCP < 2.5s ✅
  - FID < 100ms ✅
  - CLS < 0.1 ✅
- **Lazy Loading** - Images load on-demand
- **Intersection Observer** - Efficient scroll detection
- **Debounce/Throttle** - Optimized event handling
- **RequestAnimationFrame** - Smooth 60fps rendering
- **Critical CSS** - Above-the-fold optimization
- **Service Worker** - Offline capability

### 🔍 SEO Optimization
- **Structured Data** - JSON-LD Schema.org markup
- **Dynamic Sitemap** - Auto-generated XML sitemap
- **Robots.txt** - Proper crawling directives
- **Open Graph Tags** - Social media preview cards
- **Meta Tags** - Unique untuk setiap halaman
- **Semantic HTML** - Proper heading hierarchy
- **Clean URLs** - SEO-friendly routing
- **Fast Loading** - Google PageSpeed optimized

## 📦 Tech Stack

### Backend
- **PHP 7.4+** - Server-side processing
- **Apache** - Web server dengan mod_rewrite
- **cURL** - API fetching

### Frontend
- **Vanilla JavaScript (ES6+)** - No framework overhead
- **CSS3** - Modern styling dengan Custom Properties
- **HTML5** - Semantic markup

### Features
- **PWA** - Service Worker + Web App Manifest
- **Responsive Design** - Mobile-first approach
- **Cross-browser** - Chrome, Firefox, Safari, Edge

## 🚀 Installation

### Requirements
- PHP 7.4 atau lebih tinggi
- Apache dengan mod_rewrite enabled
- 512MB RAM minimum
- SSL Certificate (recommended)

### Quick Start

1. **Upload Files**
```bash
# Upload semua file ke public_html atau root directory
```

2. **Set Permissions**
```bash
chmod 755 cache/
chmod 644 .htaccess
```

3. **Configure Environment**
```bash
cp .env.example .env
# Edit .env dengan konfigurasi Anda
```

4. **Access Website**
```
https://yourdomain.com
```

### Build (Optional)
```bash
npm install
npm run build
```

## 📱 PWA Installation

### Android
1. Buka website di Chrome
2. Tap menu (⋮) → "Add to Home screen"
3. Konfirmasi instalasi
4. Icon muncul di home screen

### iOS
1. Buka website di Safari
2. Tap Share button
3. Scroll → "Add to Home Screen"
4. Konfirmasi instalasi

## 🎨 UI/UX Features Detail

### Animations
- **Hero Carousel** - Auto-rotate dengan fade effect
- **Card Hover** - 3D tilt dengan shine effect
- **Page Transitions** - Smooth fade between pages
- **Skeleton Loading** - Shimmer effect saat loading
- **Stagger Animation** - Grid items fade-in berurutan
- **Progress Bar** - Shine animation effect

### Interactions
- **Touch Optimized** - Min 44x44px touch targets
- **Swipe Gestures** - Pull to refresh (mobile)
- **Keyboard Support** - Full keyboard navigation
- **Focus Management** - Accessible focus states
- **Active States** - Visual feedback semua actions

### Responsive Design
- **Breakpoints**
  - Desktop: 1200px+
  - Tablet: 768px - 1199px
  - Mobile: < 768px
- **Safe Areas** - Support notch/cutout Android
- **Orientation** - Portrait & landscape support

## 🔍 SEO Implementation

### Technical SEO
```
✅ robots.txt - Crawling directives
✅ sitemap.xml - Dynamic page listing
✅ Structured Data - JSON-LD Schema
✅ Meta Tags - Title, description, keywords
✅ Open Graph - Social media cards
✅ Canonical URLs - Prevent duplicates
✅ Semantic HTML - H1-H6 hierarchy
✅ Alt Tags - Image descriptions
```

### Performance SEO
```
✅ Fast Loading - < 3s load time
✅ Mobile-First - Responsive design
✅ HTTPS - Secure connection
✅ Compression - GZIP enabled
✅ Caching - Browser & server cache
✅ Lazy Loading - Images on-demand
✅ Minification - CSS/JS optimized
```

### Content SEO
```
✅ Unique Titles - Setiap halaman berbeda
✅ Meta Descriptions - 150-160 karakter
✅ Internal Links - Strategic linking
✅ Breadcrumbs - Navigation path
✅ Rich Snippets - Enhanced SERP display
```

📖 **Detail Guide**: Lihat [SEO-GUIDE.md](SEO-GUIDE.md)

## 📂 File Structure

```
movietube/
├── index.php              # Homepage
├── watch.php              # Watch page
├── player.php             # Video player
├── api.php                # REST API endpoint
├── scraper.php            # Content scraper
├── seo_helper.php         # SEO utilities
├── sitemap.php            # Dynamic sitemap
├── config.php             # Configuration
├── .htaccess              # Apache routing
├── .env                   # Environment vars
│
├── app.js                 # Main JavaScript
├── animations.js          # Scroll animations
├── style.css              # Main styles
├── enhancements.css       # Advanced UI/UX
│
├── sw.js                  # Service Worker
├── manifest.json          # PWA manifest
├── robots.txt             # SEO directives
│
└── README.md              # Documentation
```

## 🎯 Performance Metrics

### Target Metrics
- **Lighthouse Score**: 90+ ✅
- **PageSpeed Score**: 85+ ✅
- **Load Time**: < 3 seconds ✅
- **Time to Interactive**: < 4 seconds ✅
- **First Contentful Paint**: < 1.5 seconds ✅

### Optimization Techniques
1. Critical CSS inlining
2. Async JavaScript loading
3. Image lazy loading
4. Browser caching (1 year)
5. GZIP compression
6. CDN for static assets
7. Database query optimization
8. API response caching

## 🛠️ Development

### Local Setup
```bash
# Clone repository
git clone https://github.com/yourusername/movietube

# Install dependencies
npm install

# Start development server
npm run dev
```

### Testing
```bash
# Run performance test
npm run lighthouse

# Test responsive design
npm run responsive-test
```

## 🔒 Security

- ✅ Input validation & sanitization
- ✅ CSRF protection
- ✅ XSS prevention
- ✅ SQL injection protection
- ✅ Secure headers
- ✅ Rate limiting
- ✅ Content Security Policy

## 🌐 Browser Support

| Browser | Version |
|---------|---------|
| Chrome  | 90+     |
| Firefox | 88+     |
| Safari  | 14+     |
| Edge    | 90+     |
| Opera   | 76+     |

## 📊 Analytics Integration

### Google Analytics 4
```html
<!-- Add to index.php -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-XXXXXXXXXX"></script>
```

### Search Console
1. Verify ownership di Google Search Console
2. Submit sitemap.xml
3. Monitor performance

## 🤝 Contributing

Contributions welcome! Please:
1. Fork repository
2. Create feature branch
3. Commit changes
4. Push to branch
5. Open Pull Request

## 📄 License

MIT License - Feel free to use for personal/commercial projects

## 🙏 Credits

- **Font Awesome** - Icons
- **Google Fonts** - Typography
- **Schema.org** - Structured data

## 📞 Support

- 📧 Email: support@movietube.com
- 🐛 Issues: GitHub Issues
- 💬 Discord: [Join Server](https://discord.gg/movietube)

## 🗺️ Roadmap

### v2.0 (Q2 2026)
- [ ] User authentication
- [ ] Comment system
- [ ] Rating & reviews
- [ ] Advanced filters
- [ ] Multi-language support

### v3.0 (Q3 2026)
- [ ] AI recommendations
- [ ] Watch parties
- [ ] Download feature
- [ ] Chromecast support

---

**Made with ❤️ for movie lovers**
