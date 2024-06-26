xml.instruct!
xml.feed "xmlns" => "http://www.w3.org/2005/Atom", "xml:lang" => "en-GB" do
  site_url = config[:site_root_uri]
  xml.title config[:blog_title]
  xml.subtitle config[:blog_subtitle]
  xml.id URI.join(site_url, blog.options.prefix.to_s)
  xml.link "href" => URI.join(site_url, blog.options.prefix.to_s)
  xml.link "href" => URI.join(site_url, current_page.path), "rel" => "self"
  xml.rights "&copy; " + config[:blog_author] + " 2014-" + Time.now.utc.strftime("%Y"), "type" => "html"
  xml.updated(blog.articles.first.date.to_time.iso8601) unless blog.articles.empty?
  xml.author do
    xml.name config[:blog_author]
    xml.uri "https://martincostello.com"
    xml.email "martin@martincostello.com"
  end
  
  blog.articles[0..5].each do |article|
    xml.entry do
      xml.title article.title
      xml.link "rel" => "alternate", "href" => URI.join(site_url, article.url)
      xml.id URI.join(site_url, article.url)
      xml.published article.date.to_time.iso8601
      xml.updated File.mtime(article.source_file).iso8601
      xml.author { xml.name config[:blog_author] }
      xml.summary article.summary, "type" => "html"
      xml.content article.body, "type" => "html"
    end
  end
end
