###
# Blog settings
###

Time.zone = "UTC"

activate :blog do |blog|

  blog.permalink = "{title}"
  blog.sources = "{year}-{month}-{day}-{title}.html"
  blog.taglink = "tags/{tag}.html"
  # blog.layout = "layout"
  blog.summary_separator = /(READMORE)/
  blog.summary_length = 250
  blog.year_link = "{year}.html"
  blog.month_link = "{year}/{month}.html"
  blog.day_link = "{year}/{month}/{day}.html"
  blog.default_extension = ".md"

  blog.tag_template = "tag.html"
  blog.calendar_template = "calendar.html"

  # Enable pagination
  # blog.paginate = true
  # blog.per_page = 10
  # blog.page_link = "page/{num}"
end

activate :directory_indexes

page "/feed.xml", layout: false
page "/humans.txt", layout: false, :directory_index => false
page "/hostingstart.html", layout: false, :directory_index => false
page "/robots.txt", layout: false, :directory_index => false
page "/sitemap.xml", layout: false

###
# Compass
###

# Change Compass configuration
# compass_config do |config|
#   config.output_style = :compact
# end

###
# Page options, layouts, aliases and proxies
###

# Per-page layout changes:
#
# With no layout
# page "/path/to/file.html", :layout => false
#
# With alternative layout
# page "/path/to/file.html", :layout => :otherlayout
#
# A path which all have the same layout
# with_layout :admin do
#   page "/admin/*"
# end

# Proxy pages (https://middlemanapp.com/advanced/dynamic_pages/)
# proxy "/this-page-has-no-template.html", "/template-file.html", :locals => {
#  :which_fake_page => "Rendering a fake page with a local variable" }

###
# Helpers
###

# Automatic image dimensions on image_tag helper
# activate :automatic_image_sizes

# Reload the browser automatically whenever files change
configure :development do
  # activate :livereload
  set :site_root_uri, "https://localhost/"
  set :render_analytics, false
end

# Methods defined in the helpers block are available in templates
# helpers do
#   def some_helper
#     "Helping"
#   end
# end

set :css_dir, 'styles'

set :js_dir, 'scripts'

set :images_dir, 'images'

# Build-specific configuration
configure :build do

  activate :minify_html, remove_input_attributes: false
  activate :minify_css
  activate :minify_javascript
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
set :analytics_id, "UA-42907618-4"
set :blog_author, "Martin Costello"
set :blog_title, "Martin Costello's Blog"
set :blog_subtitle, "The blog of a software developer and tester."
set :facebook_profile, "10100867762061905"
set :site_verification_bing, "D6C2E7551C902F1A396D8564C6452930"
set :site_verification_google, "ji6SNsPQEbNQmF252sQgQFswh-b6cDnNOa3AHvgo4J0"
set :twitter_handle, "martin_costello"
