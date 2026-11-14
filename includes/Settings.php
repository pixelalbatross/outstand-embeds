<?php

namespace Outstand\WP\Embeds;

defined( 'ABSPATH' ) || exit;

/**
 * The embed consent settings, in a section of Settings > Reading: the cookie category that allows
 * the embeds, and whether embeds wait for consent when no consent tool is active.
 */
class Settings extends BaseModule {

	/**
	 * Option that holds the consent category slug.
	 *
	 * @var string
	 */
	public const CATEGORY_OPTION = 'outstand_embeds_consent_category';

	/**
	 * Option that holds the "require consent" flag.
	 *
	 * @var string
	 */
	public const REQUIRE_CONSENT_OPTION = 'outstand_embeds_require_consent';

	/**
	 * Default consent category. Facebook and Instagram set advertising cookies.
	 *
	 * @var string
	 */
	public const DEFAULT_CATEGORY = 'advertisement';

	/**
	 * Settings section ID on the Reading page.
	 *
	 * @var string
	 */
	public const SECTION_ID = 'os_embeds_section';

	/**
	 * Settings field ID of the consent category.
	 *
	 * @var string
	 */
	public const CATEGORY_FIELD_ID = 'os_embeds_consent_category';

	/**
	 * Settings field ID of the "require consent" flag.
	 *
	 * @var string
	 */
	public const REQUIRE_CONSENT_FIELD_ID = 'os_embeds_require_consent';

	/**
	 * Settings page that shows the section.
	 *
	 * @var string
	 */
	public const PAGE = 'reading';

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'register_settings' ] );
		add_action( 'admin_init', [ $this, 'add_settings_fields' ] );
	}

	/**
	 * Registers the two options on the Reading settings page.
	 *
	 * @return void
	 */
	public function register_settings(): void {

		register_setting(
			self::PAGE,
			self::CATEGORY_OPTION,
			[
				'type'              => 'string',
				'sanitize_callback' => [ $this, 'sanitize_category' ],
				'default'           => self::DEFAULT_CATEGORY,
			]
		);

		register_setting(
			self::PAGE,
			self::REQUIRE_CONSENT_OPTION,
			[
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			]
		);
	}

	/**
	 * Adds the section and its fields to the Reading settings page.
	 *
	 * @return void
	 */
	public function add_settings_fields(): void {

		add_settings_section(
			self::SECTION_ID,
			__( 'Embeds', 'outstand-embeds' ),
			[ $this, 'render_section' ],
			self::PAGE,
			[
				'before_section' => sprintf( '<div id="%s">', esc_attr( self::SECTION_ID ) ),
				'after_section'  => '</div>',
			]
		);

		add_settings_field(
			self::CATEGORY_FIELD_ID,
			__( 'Consent category', 'outstand-embeds' ),
			[ $this, 'render_category_field' ],
			self::PAGE,
			self::SECTION_ID,
			[ 'label_for' => self::CATEGORY_FIELD_ID ]
		);

		add_settings_field(
			self::REQUIRE_CONSENT_FIELD_ID,
			__( 'Require consent', 'outstand-embeds' ),
			[ $this, 'render_require_consent_field' ],
			self::PAGE,
			self::SECTION_ID
		);
	}

	/**
	 * Renders the section introduction.
	 *
	 * @return void
	 */
	public function render_section(): void {

		printf(
			'<p>%s</p>',
			esc_html__( 'Facebook and Instagram embeds set third-party cookies. When a consent tool is active, they load only after the visitor accepts the cookie category below.', 'outstand-embeds' )
		);
	}

	/**
	 * Renders the consent category text input.
	 *
	 * @return void
	 */
	public function render_category_field(): void {

		printf(
			'<input type="text" id="%1$s" name="%2$s" value="%3$s" class="regular-text" aria-describedby="%1$s-description" />',
			esc_attr( self::CATEGORY_FIELD_ID ),
			esc_attr( self::CATEGORY_OPTION ),
			esc_attr( self::get_category() )
		);

		printf(
			'<p class="description" id="%1$s-description">%2$s</p>',
			esc_attr( self::CATEGORY_FIELD_ID ),
			esc_html__( 'The slug of the cookie category in your consent tool, for example "advertisement" or "functional" in CookieYes. Use the category in which the consent tool lists the Facebook and Instagram cookies.', 'outstand-embeds' )
		);
	}

	/**
	 * Renders the "require consent" checkbox.
	 *
	 * @return void
	 */
	public function render_require_consent_field(): void {

		printf(
			'<label for="%1$s"><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s /> %4$s</label>',
			esc_attr( self::REQUIRE_CONSENT_FIELD_ID ),
			esc_attr( self::REQUIRE_CONSENT_OPTION ),
			checked( (bool) get_option( self::REQUIRE_CONSENT_OPTION, false ), true, false ),
			esc_html__( 'Show only a notice and a link when no consent tool is active', 'outstand-embeds' )
		);

		printf(
			'<p class="description">%s</p>',
			esc_html__( 'When this is off and no consent tool is active, embeds load at once.', 'outstand-embeds' )
		);
	}

	/**
	 * Keeps only a slug, and falls back to the default when the value is empty.
	 *
	 * @param  mixed $value The submitted value.
	 * @return string
	 */
	public function sanitize_category( $value ): string {

		$slug = sanitize_key( (string) $value );

		if ( '' === $slug ) {
			return self::DEFAULT_CATEGORY;
		}

		return $slug;
	}

	/**
	 * The stored consent category, or the default when it is empty.
	 *
	 * @return string
	 */
	public static function get_category(): string {

		$stored = sanitize_key( (string) get_option( self::CATEGORY_OPTION, self::DEFAULT_CATEGORY ) );

		if ( '' === $stored ) {
			return self::DEFAULT_CATEGORY;
		}

		return $stored;
	}

	/**
	 * The consent category of an embed. An empty string means that the embed needs no consent.
	 *
	 * @param  string $provider The provider slug, for example "facebook".
	 * @param  string $url      The embedded URL.
	 * @return string
	 */
	public static function get_consent_category( string $provider, string $url ): string {

		/**
		 * Filters the consent category of an embed.
		 *
		 * @param string $category The category slug. Return an empty string to load the embed without consent.
		 * @param string $provider The provider slug: "facebook" or "instagram".
		 * @param string $url      The embedded URL.
		 */
		$category = apply_filters( 'outstand_embeds_consent_category', self::get_category(), $provider, $url );

		if ( ! is_string( $category ) ) {
			$category = self::get_category();
		}

		return sanitize_key( $category );
	}

	/**
	 * Whether embeds wait for consent when no consent tool is active.
	 *
	 * @return bool
	 */
	public static function is_consent_required(): bool {

		$required = (bool) get_option( self::REQUIRE_CONSENT_OPTION, false );

		/**
		 * Filters whether embeds wait for consent when no consent tool is active. When true, the
		 * embed shows only a notice and a link. When false, the embed loads at once.
		 *
		 * @param bool $required The stored setting.
		 */
		return (bool) apply_filters( 'outstand_embeds_require_consent', $required );
	}
}
