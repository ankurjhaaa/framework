<?php

namespace Kite\Core;

/**
 * Auto-SEO Engine
 * Automatically generates standard Meta tags, OpenGraph tags, and Twitter Card tags.
 */
class Seo
{
    protected static ?Seo $instance = null;
    
    protected array $data = [
        'title'       => '',
        'description' => '',
        'image'       => '',
        'favicon'     => '/favicon.ico', // Default favicon
        'url'         => '',
        'type'        => 'website',
        'site_name'   => ''
    ];

    /**
     * Singleton instance.
     */
    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
            
            // Set default URL to current URL
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $uri = $_SERVER['REQUEST_URI'] ?? '/';
            self::$instance->data['url'] = $protocol . "://" . $host . $uri;
        }
        return self::$instance;
    }

    /**
     * Set the SEO Title.
     */
    public function title(string $title): self
    {
        $this->data['title'] = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        return $this;
    }

    /**
     * Set the SEO Description.
     */
    public function description(string $description): self
    {
        $this->data['description'] = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
        return $this;
    }

    /**
     * Set the SEO Image (for social previews: og:image, twitter:image).
     */
    public function image(string $url): self
    {
        $this->data['image'] = filter_var($url, FILTER_SANITIZE_URL);
        return $this;
    }

    /**
     * Set a custom Favicon (overrides default /favicon.ico).
     */
    public function favicon(string $url): self
    {
        $this->data['favicon'] = filter_var($url, FILTER_SANITIZE_URL);
        return $this;
    }

    /**
     * Generate and output all the HTML Meta Tags.
     */
    public function render(): string
    {
        $html = [];
        
        // Favicon
        if (!empty($this->data['favicon'])) {
            $html[] = "<link rel=\"icon\" href=\"{$this->data['favicon']}\">";
        }
        
        // Basic SEO
        if (!empty($this->data['title'])) {
            $html[] = "<title>{$this->data['title']}</title>";
        }
        if (!empty($this->data['description'])) {
            $html[] = "<meta name=\"description\" content=\"{$this->data['description']}\">";
        }

        // Canonical URL
        if (!empty($this->data['url'])) {
            $html[] = "<link rel=\"canonical\" href=\"{$this->data['url']}\">";
        }

        // OpenGraph (Facebook, LinkedIn, WhatsApp)
        if (!empty($this->data['title'])) {
            $html[] = "<meta property=\"og:title\" content=\"{$this->data['title']}\">";
        }
        if (!empty($this->data['description'])) {
            $html[] = "<meta property=\"og:description\" content=\"{$this->data['description']}\">";
        }
        if (!empty($this->data['image'])) {
            $html[] = "<meta property=\"og:image\" content=\"{$this->data['image']}\">";
        }
        if (!empty($this->data['url'])) {
            $html[] = "<meta property=\"og:url\" content=\"{$this->data['url']}\">";
        }
        $html[] = "<meta property=\"og:type\" content=\"{$this->data['type']}\">";
        if (!empty($this->data['site_name'])) {
            $html[] = "<meta property=\"og:site_name\" content=\"{$this->data['site_name']}\">";
        }

        // Twitter Cards
        $html[] = "<meta name=\"twitter:card\" content=\"summary_large_image\">";
        if (!empty($this->data['title'])) {
            $html[] = "<meta name=\"twitter:title\" content=\"{$this->data['title']}\">";
        }
        if (!empty($this->data['description'])) {
            $html[] = "<meta name=\"twitter:description\" content=\"{$this->data['description']}\">";
        }
        if (!empty($this->data['image'])) {
            $html[] = "<meta name=\"twitter:image\" content=\"{$this->data['image']}\">";
        }

        return implode("\n    ", $html);
    }
}
