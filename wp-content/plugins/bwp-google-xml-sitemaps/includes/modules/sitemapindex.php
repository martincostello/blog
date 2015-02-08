<?php
/**
 * Copyright (c) 2014 Khang Minh <betterwp.net>
 * @license http://www.gnu.org/licenses/gpl.html GNU GENERAL PUBLIC LICENSE
 * @package BWP Google XML Sitemaps
 */

class BWP_GXS_MODULE_INDEX extends BWP_GXS_MODULE
{
	protected $requested_modules = array();

	public function __construct($requested)
	{
		// this contains a list of all sitemaps that need to be included in the
		// sitemapindex, along with their data
		$this->requested_modules = $requested;

		// @since 1.3.0 get sitemap log to populate sitemaps' last modified date data
		global $bwp_gxs;
		$this->sitemap_logs = $bwp_gxs->get_sitemap_logs();
	}

	/**
	 * Get a sitemap's last modified date using either the log or filesystem
	 *
	 * @since 1.3.0
	 */
	private function _get_sitemap_lastmod($sitemap_name)
	{
		if (!empty($this->sitemap_logs[$sitemap_name]))
		{
			// there's a log entry in db, take last modified date from it in
			// Unix timestamp format
			return $this->sitemap_logs[$sitemap_name];
		}

		$sitemap_file = bwp_gxs_get_filename($sitemap_name);

		if ($filemtime = @filemtime($sitemap_file))
		{
			// if we can get a last modified Unix timestamp from the
			// filesystem, use that one as a last resort
			return $filemtime;
		}

		// all fail, no lastmod should be display
		return false;
	}

	/**
	 * This is the main function that generates our data.
	 *
	 * If your module deals with heavy queries, for example selecting all posts
	 * from the database, you should not use build_data() directly but rather
	 * use generate_data(). @see post.php for more details.
	 */
	protected function build_data()
	{
		global $wpdb, $bwp_gxs;

		$post_count_array = array();

		if ('yes' == $bwp_gxs->options['enable_sitemap_split_post'])
		{
			// @since 1.3.0 build a list of post IDs that we need to exclude so
			// that we can correctly split post-based sitemaps
			$excluded_posts = array();
			$post_types     = get_post_types(array('public' => true));

			foreach ($post_types as $post_type_name)
			{
				$excluded_posts = array_merge(
					$excluded_posts,
					apply_filters('bwp_gxs_excluded_posts', array(), $post_type_name)
				);
			}

			$exclude_post_sql = sizeof($excluded_posts) > 0
				? ' AND p.ID NOT IN (' . implode(',', $excluded_posts) . ') '
				: '';

			// we need to split post-based sitemaps
			$post_count_query = '
				SELECT
					COUNT(p.ID) as total,
					p.post_type
				FROM ' . $wpdb->posts . " p
				WHERE p.post_status = 'publish'
					$exclude_post_sql
					AND p.post_password = ''" . '
				GROUP BY p.post_type
			';

			$post_counts = $wpdb->get_results($post_count_query);

			foreach ($post_counts as $count)
				$post_count_array[$count->post_type] = $count->total;

			unset($post_counts);
			unset($count);
		}

		$data = array();

		foreach ($this->requested_modules as $item)
		{
			// all sitemap should have the regular sitemap names, unless
			// appended with part numbers (i.e. `partxxx`)
			$module_name  = $item['module_name'];
			$sitemap_name = $module_name;

			$passed = false; // whether this item's data has already been passed back

			$data             = $this->init_data($data);
			$data['location'] = $this->get_sitemap_url($module_name);

			$custom_modules = array(
				'post_google_news',
				'page_external'
			);

			if (in_array($item['module'], array('post', 'page'))
				&& !in_array($module_name, $custom_modules)
			) {
				// handle normal post-based sitemaps, including the somewhat
				// special 'page' sitemap, but excluding any custom modules,
				// i.e. modules that are registered under post-based sitemap but
				// should not be checked for splitting functionalty
				$post_type = $item['sub_module'];

				if (empty($post_count_array[$post_type]))
				{
					// @since 1.3.0 if this post sitemap does not any item,
					// ignore it completely
					continue;
				}

				$split_limit = empty($bwp_gxs->options['input_split_limit_post'])
					? $bwp_gxs->options['input_item_limit']
					: $bwp_gxs->options['input_split_limit_post'];

				if ('yes' == $bwp_gxs->options['enable_sitemap_split_post']
					&& $post_count_array[$post_type] > $split_limit
				) {
					// if we have a matching post_type and the total number
					// of posts reach the split limit, we will split this
					// post sitemap accordingly
					$num_part = ceil($post_count_array[$post_type] / $split_limit);
					$num_part = (int) $num_part;

					if (1 < $num_part)
					{
						// more than one parts, split this post-based sitemap
						for ($i = 1; $i <= $num_part; $i++)
						{
							// append part number to sitemap name
							$sitemap_name = $module_name . '_part' . $i;
							$data['location'] = $this->get_sitemap_url($sitemap_name);

							$lastmod = $this->_get_sitemap_lastmod($sitemap_name);
							$data['lastmod'] = $lastmod ? $this->format_lastmod($lastmod) : '';
							$data['lastmod'] = apply_filters('bwp_gxs_sitemap_lastmod', $data['lastmod'], $lastmod, $item, $i);

							$this->data[] = $data;
						}

						$passed = true;
					}
				}
			}

			if (false == $passed)
			{
				// only do this if data has not been passed back already
				// @since 1.3.0 use the correct mechanism to determine last
				// modified date for a sitemap file.
				$lastmod = $this->_get_sitemap_lastmod($sitemap_name);

				$data['lastmod'] = $lastmod ? $this->format_lastmod($lastmod) : '';
				$data['lastmod'] = apply_filters('bwp_gxs_sitemap_lastmod', $data['lastmod'], $lastmod, $item, 0);

				$this->data[] = $data;
			}
		}
	}
}
