<?php
/**
 * SEO preview checklist thresholds for the ConWoo wizard.
 *
 * @package ConceptPlug
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class ConWoo_Seo_Preview_Config
 */
class ConWoo_Seo_Preview_Config {

	/**
	 * Thresholds exposed to the create-product preview UI.
	 *
	 * @return array<string, int>
	 */
	public static function to_js_array() {
		return array(
			'titleMin'       => 40,
			'titleMax'       => 60,
			'titleWarnMin'   => 30,
			'titleWarnMax'   => 70,
			'metaMin'        => 120,
			'metaMax'        => 160,
			'metaWarnMin'    => 100,
			'metaWarnMax'    => 170,
			'wordsMin'       => 300,
			'wordsWarnMin'   => 150,
			'shortDescMin'   => 50,
			'shortDescWarn'  => 20,
			'tagsMin'        => 3,
			'tagsMax'        => 8,
			'slugWarnMax'    => 60,
		);
	}
}
