<?php

namespace MediaWiki\Extension\UploadWizard;

/**
 * Static class with methods for interacting with the Upload Wizards configuration.
 *
 * @file
 * @ingroup Upload
 *
 * @since 1.2
 *
 * @license GPL-2.0-or-later
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class Config {

	/**
	 * Returns true if any of the keys of an array is a string
	 *
	 * @param array $array
	 *
	 * @return bool
	 */
	private static function isAssoc( $array ) {
		return (bool)count( array_filter( array_keys( $array ), 'is_string' ) );
	}

	/**
	 * Same functionality as array_merge_recursive, but sanely
	 * It treats 'normal' integer indexed arrays as scalars, and does
	 * not recurse into them. Associative arrays are recursed into
	 *
	 * @param array $array
	 * @param array $array1
	 *
	 * @return array Yet another array, sanely replacing contents of $array with $array1
	 */
	public static function arrayReplaceSanely( $array, $array1 ) {
		$newArray = [];

		foreach ( $array as $key => $value ) {
			if ( array_key_exists( $key, $array1 ) ) {
				switch ( gettype( $value ) ) {
					case "array":
						if ( self::isAssoc( $array[$key] ) ) {
							$newArray[$key] = self::arrayReplaceSanely( $array[$key], $array1[$key] );
							break;
						}
						# fall through
					default:
						$newArray[$key] = $array1[$key];
						break;
				}
			} else {
				$newArray[$key] = $array[$key];
			}
		}
		return array_merge( $newArray, array_diff_key( $array1, $array ) );
	}

	/**
	 * Holder for configuration specified via url arguments.
	 * This will override other config when returned via getConfig.
	 *
	 * @since 1.2
	 * @var array
	 */
	protected static $urlConfig = [];

	/**
	 * Returns the globally configuration, optionally combined with campaign specific
	 * configuration.
	 *
	 * @since 1.2
	 *
	 * @param string|null $campaignName
	 *
	 * @return array
	 */
	public static function getConfig( $campaignName = null ) {
		global $wgUploadWizardConfig;
		static $mergedConfig = false;

		if ( !$mergedConfig ) {
			$wgUploadWizardConfig = self::arrayReplaceSanely(
				self::getDefaultConfig(),
				$wgUploadWizardConfig
			);
			$mergedConfig = true;
		}

		if ( $campaignName !== null ) {
			$wgUploadWizardConfig = self::arrayReplaceSanely(
				$wgUploadWizardConfig,
				self::getCampaignConfig( $campaignName )
			);
		}

		return array_replace_recursive( $wgUploadWizardConfig, self::$urlConfig );
	}

	/**
	 * Returns the value of a single configuration setting.
	 *
	 * @since 1.2
	 *
	 * @param string $settingName
	 * @param string|null $campaignName
	 *
	 * @return mixed
	 */
	public static function getSetting( $settingName, $campaignName = null ) {
		$config = self::getConfig( $campaignName );
		return $config[$settingName];
	}

	/**
	 * Sets a configuration setting provided by URL.
	 * This will override other config when returned via getConfig.
	 *
	 * @param string $name
	 * @param mixed $value
	 *
	 * @since 1.2
	 */
	public static function setUrlSetting( $name, $value ) {
		self::$urlConfig[$name] = $value;
	}

	/**
	 * Returns the default global config, from UploadWizard.config.php.
	 *
	 * @since 1.2
	 *
	 * @return array
	 */
	protected static function getDefaultConfig() {
		$configPath = dirname( __DIR__ ) . '/UploadWizard.config.php';
		return is_file( $configPath ) ? include $configPath : [];
	}

	/**
	 * Returns the configuration of the specified campaign,
	 * or an empty array when the campaign is not found or not enabled.
	 *
	 * @since 1.2
	 *
	 * @param string $campaignName
	 *
	 * @return array
	 */
	protected static function getCampaignConfig( $campaignName ) {
		if ( $campaignName !== null ) {
			$campaign = Campaign::newFromName( $campaignName );

			if ( $campaign !== false && $campaign->getIsEnabled() ) {
				return $campaign->getParsedConfig();
			}
		}

		return [];
	}

	/**
	 * Get a list of available third party licenses from the config.
	 *
	 * @since 1.2
	 *
	 * @return array
	 */
	public static function getThirdPartyLicenses() {
		$licensing = self::getSetting( 'licensing' );
		$thirdParty = $licensing['thirdParty'];
		$licenses = [];

		foreach ( $thirdParty['licenseGroups'] as $group ) {
			$licenses = array_merge( $licenses, $group['licenses'] );
		}

		return $licenses;
	}
}
