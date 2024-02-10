xml.instruct!
xml.urlset "xmlns" => "http://www.sitemaps.org/schemas/sitemap/0.9" do
  site_url = config[:site_root_uri]

  xml.url do
    xml.loc site_url
    xml.lastmod File.mtime(sitemap.resources.first.source_file).iso8601
    xml.changefreq "monthly"
    xml.priority "0.5"
  end

  xml.url do
    xml.loc URI.join(site_url, "archive")
    xml.lastmod File.mtime("source/archive.html.erb").iso8601
    xml.changefreq "monthly"
    xml.priority "0.5"
  end

  xml.url do
    xml.loc URI.join(site_url, "about-me")
    xml.lastmod File.mtime("source/about-me.html.erb").iso8601
    xml.changefreq "monthly"
    xml.priority "0.5"
  end

  blog.articles.each do |article|
    xml.url do
      xml.loc URI.join(site_url, article.url)
      xml.lastmod File.mtime(article.source_file).iso8601
      xml.changefreq "monthly"
      xml.priority "0.5"
    end
  end
end
