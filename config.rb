# Activate and configure extensions

activate :autoprefixer do |prefix|
  prefix.browsers = "last 2 versions"
end

###
# Blog settings
###

Time.zone = "Europe/London"

activate :blog do |blog|

  blog.permalink = "{title}"
  blog.sources = "{year}-{month}-{day}-{title}.html"
  blog.taglink = "tags/{tag}.html"

  blog.summary_separator = /(READMORE)/
  blog.summary_length = 250
  blog.year_link = "{year}.html"
  blog.month_link = "{year}/{month}.html"
  blog.day_link = "{year}/{month}/{day}.html"
  blog.default_extension = ".md"

  blog.tag_template = "tag.html"
  blog.calendar_template = "calendar.html"
end

activate :directory_indexes

page "/humans.txt", layout: false, :directory_index => false
page "/hostingstart.html", layout: false, :directory_index => false
page "/robots.txt", layout: false, :directory_index => false
page "/*.json", layout: false
page "/*.txt", layout: false
page "/*.xml", layout: false

###
# Helpers
###

# Reload the browser automatically whenever files change
configure :development do
  # activate :livereload
  set :site_root_uri, "https://localhost/"
  set :render_analytics, false
end

set :css_dir, 'styles'

set :js_dir, 'scripts'

set :images_dir, 'images'

# Build-specific configuration
configure :build do

  activate :minify_html, remove_input_attributes: false
  activate :minify_css
  activate :minify_javascript, compressor: Terser.new
  activate :gzip
  activate :asset_hash

  set :site_root_uri, "https://blog.martincostello.com/"
  set :render_analytics, true
end

##
# Syntax Configuration
##

activate :syntax
set :markdown_engine, :redcarpet
set :markdown, :fenced_code_blocks => true

##
# Custom Configuration
##

set :cdn_domain, "cdn.martincostello.com"
set :site_domain, "blog.martincostello.com"
set :site_root_uri_canonical, "https://blog.martincostello.com/"
set :analytics_id, "G-XJFV74HRL6"
set :blog_author, "Martin Costello"
set :blog_title, "Martin Costello's Blog"
set :blog_subtitle, "The blog of a software developer and tester."
set :facebook_profile, "10100867762061905"
set :site_verification_bing, "D6C2E7551C902F1A396D8564C6452930"
set :site_verification_google, "ji6SNsPQEbNQmF252sQgQFswh-b6cDnNOa3AHvgo4J0"
set :twitter_handle, "martin_costello"
