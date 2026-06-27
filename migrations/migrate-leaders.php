<?php
/**
 * Migrate the current site's "team" CPT into the People CPT as role "leader".
 *
 * Run with:  wp eval-file migrate-leaders.php
 *   preview:  wp eval-file migrate-leaders.php dry
 *
 * Per leader:
 *   1. If a People post with the same name already exists (e.g. Dustin Radtke,
 *      already an author/presenter), reuse it: add the "leader" role, OVERWRITE
 *      post_content with the richer team bio (+ Did You Know block), and fill
 *      job_position / linkedin_url / name fields only if currently empty.
 *   2. Otherwise create a new People post (role "leader") with the assembled
 *      content.
 *   3. Sideload the headshot from the live site as featured image if missing.
 *
 * Idempotent. Photos de-duplicated by source URL (_msw_source_url meta).
 */

$DRY = ( isset( $args ) && in_array( 'dry', $args, true ) )
    || ( isset( $argv ) && in_array( 'dry', $argv, true ) );

if ( ! function_exists( 'media_sideload_image' ) ) {
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
}

if ( ! term_exists( 'leader', 'person_role' ) ) {
	if ( ! $DRY ) {
		wp_insert_term( 'Leader', 'person_role', array( 'slug' => 'leader' ) );
	}
	WP_CLI::log( '[term] ensured person_role: leader' );
}

/** Safety net: strip any stray CDATA wrapper from a value. */
function msw_clean( $s ) {
	if ( null === $s ) { return ''; }
	if ( preg_match( '/^\s*<!\[CDATA\[(.*?)\]\]>\s*$/s', $s, $m ) ) {
		return trim( $m[1] );
	}
	return trim( $s );
}

function msw_find_person( $name ) {
	$base = trim( preg_split( '/,/', $name )[0] );
	$q = get_posts( array(
		'post_type' => 'people', 'post_status' => 'any',
		'posts_per_page' => 1, 'fields' => 'ids', 'title' => $name,
	) );
	if ( $q ) { return $q[0]; }
	$all = get_posts( array(
		'post_type' => 'people', 'post_status' => 'any',
		'posts_per_page' => -1, 'fields' => 'ids',
	) );
	foreach ( $all as $pid ) {
		$existing_base = trim( preg_split( '/,/', get_the_title( $pid ) )[0] );
		if ( strcasecmp( $existing_base, $base ) === 0 ) { return $pid; }
	}
	return 0;
}

function msw_sideload_unique( $url, $post_id ) {
	$existing = get_posts( array(
		'post_type' => 'attachment', 'post_status' => 'inherit',
		'posts_per_page' => 1, 'fields' => 'ids',
		'meta_key' => '_msw_source_url', 'meta_value' => $url,
	) );
	if ( $existing ) { return $existing[0]; }
	$tmp = download_url( $url );
	if ( is_wp_error( $tmp ) ) { return $tmp; }
	$file_array = array(
		'name' => basename( parse_url( $url, PHP_URL_PATH ) ),
		'tmp_name' => $tmp,
	);
	$att_id = media_handle_sideload( $file_array, $post_id );
	if ( is_wp_error( $att_id ) ) { @unlink( $tmp ); return $att_id; }
	update_post_meta( $att_id, '_msw_source_url', $url );
	return $att_id;
}

$leaders = array(
    array(
        'name' => 'Mike Shea',
        'slug' => 'mike-shea',
        'job_position' => 'Chief Operating Officer',
        'linkedin_url' => 'https://www.linkedin.com/company/momentivesoftware/',
        'first_name' => 'Mike',
        'last_name' => 'Shea',
        'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2025/01/Mike-S.webp',
        'content' => '<!-- wp:paragraph -->
<p>Mike is an experienced executive with a track record of improving customer experience, team member engagement and P&amp;L results. He has been successful across multiple industries including Banking, Technology Enabled Business Services and Healthcare Services. He has significant M&amp;A experience and has led multiple business integration programs.  Throughout his career, he has greatly valued, and been known as a developer of diverse and engaged teams that are committed to delivering consistently great customer experiences.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>As Momentive Software’s Chief Operating Officer, Mike looks for ways to enhance business processes to improve customer experience and fuel business growth.  He prioritizes employee engagement and customer retention by promoting alignment, trust and collaboration across teams while seeking new ways to deliver even more value to customers.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Prior to joining Momentive Software, Mike served as senior director, consumer and business banking enablement at TCF Bank where he led customer facing and business operations teams following M&amp;A activity and resulted in improved operational efficiencies and revenue growth. Prior to this, Mike held business operations leadership roles at healthcare technology service provider, nThrive, Stream Global Services and HCM software provider, Ceridian Corporation (now Dayforce) and GE Capital.</p>
<!-- /wp:paragraph -->

<!-- wp:group {"className":"round","style":{"spacing":{"padding":{"top":"var:preset|spacing|x-small","bottom":"var:preset|spacing|x-small","left":"var:preset|spacing|x-small","right":"var:preset|spacing|x-small"}}},"backgroundColor":"superlight-accent","layout":{"type":"constrained"}} -->
<div class="wp-block-group round has-superlight-accent-background-color has-background" style="padding-top:var(--wp--preset--spacing--x-small);padding-right:var(--wp--preset--spacing--x-small);padding-bottom:var(--wp--preset--spacing--x-small);padding-left:var(--wp--preset--spacing--x-small)"><!-- wp:momentive/icon-block {"iconId":"bx-bulb","shape":"none","backgroundColor":"none","iconColor":"secondary","className":"inline-block"} /-->
<!-- wp:paragraph {"fontSize":"medium"} -->
<p class="has-medium-font-size"><strong>Did you know?</strong></p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>Mike holds a bachelor’s degree in business administration and information management from the University of North Dakota.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Mike lives in Prior Lake Minnesota.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->',
    ),
    array(
        'name' => 'Dustin Radtke',
        'slug' => 'dustin-radtke',
        'job_position' => 'Chief AI Officer',
        'linkedin_url' => 'https://www.linkedin.com/company/momentivesoftware/',
        'first_name' => 'Dustin',
        'last_name' => 'Radtke',
        'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2025/01/Dustin.webp',
        'content' => '<!-- wp:paragraph -->
<p>Dustin is a software technology leader with a proven track record of guiding teams to deliver disruptive product innovation to the market. He excels at building, leading and transforming global products and technology portfolios to support business objectives and customer growth.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>As Momentive’s Chief AI Officer, Dustin leads a global team dedicated to the company’s AI-driven innovation and product transformation through the development of its AI-native platform, MomentiveIQ.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Before joining Momentive, Dustin served as Chief Technology Officer and most recently held the Chief Operating Officer role at OnSolve, where he directed the product strategy, operations and development for the company’s market-leading critical event management solutions. Prior to OnSolve, Dustin held product leadership positions at Honeywell, JDA Software (now known as Blue Yonder), RedPrairie and Teklynx.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Dustin holds bachelor’s degrees in accounting and management information systems from the University of Wisconsin-Milwaukee.</p>
<!-- /wp:paragraph -->

<!-- wp:group {"className":"round","style":{"spacing":{"padding":{"top":"var:preset|spacing|x-small","bottom":"var:preset|spacing|x-small","left":"var:preset|spacing|x-small","right":"var:preset|spacing|x-small"}}},"backgroundColor":"superlight-accent","layout":{"type":"constrained"}} -->
<div class="wp-block-group round has-superlight-accent-background-color has-background" style="padding-top:var(--wp--preset--spacing--x-small);padding-right:var(--wp--preset--spacing--x-small);padding-bottom:var(--wp--preset--spacing--x-small);padding-left:var(--wp--preset--spacing--x-small)"><!-- wp:momentive/icon-block {"iconId":"bx-bulb","shape":"none","backgroundColor":"none","iconColor":"secondary","className":"inline-block"} /-->
<!-- wp:paragraph {"fontSize":"medium"} -->
<p class="has-medium-font-size"><strong>Did you know?</strong></p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>Dustin lives in Atlanta where he can be found supporting the many activities his kids are involved in as well as perusing the local car show scene. Dustin supports his school PTA and Foundation activities along with juvenile diabetes and breast cancer support and research foundations.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->',
    ),
    array(
        'name' => 'Kristen Anderson',
        'slug' => 'kristen-anderson',
        'job_position' => 'Chief Legal Officer & Corporate Secretary',
        'linkedin_url' => 'https://www.linkedin.com/company/momentivesoftware/',
        'first_name' => 'Kristen',
        'last_name' => 'Anderson',
        'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2025/01/Kristen.webp',
        'content' => '<!-- wp:paragraph -->
<p>Kristen is a seasoned legal executive and strategic advisor with more than 25 years of experience guiding growth-oriented companies, including fintech startups and private equity-backed SaaS enterprises, through complex legal, regulatory, and governance landscapes. She brings a distinguished record of enabling revenue acceleration while safeguarding enterprise value, drawing on extensive experience in both in-house and outside counsel roles. Kristen is recognized for aligning legal strategy with business objectives, partnering closely with executive leadership, founders, investors, and cross-functional teams to deliver pragmatic, risk-calibrated solutions that drive measurable outcomes.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>As Momentive’s Chief Legal Officer, Kristen leads the global legal and corporate governance functions. She defines and executes the company’s legal strategy, builds scalable legal and compliance infrastructure, and strengthens governance frameworks to support rapid growth and value creation. In her role as Corporate Secretary, she serves as a principal advisor to the Board of Directors and its committees, enhances board and committee effectiveness, and advances best-in-class governance practices. Kristen plays a central role in strategic transactions, commercial optimization, regulatory compliance, risk management, and operational scaling, ensuring the organization is positioned for sustainable expansion and successful investor outcomes.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Kristen has held senior legal roles within both global enterprises and high-growth fintech startups, where she established foundational legal and compliance programs, negotiated complex commercial and payments agreements, supported capital raising and M&amp;A activity, and implemented governance structures designed to scale alongside evolving business models.&nbsp;</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Kristen earned her undergraduate degree from the University of Tennessee and her Juris Doctor from The University of Tennessee College of Law, both cum laude.</p>
<!-- /wp:paragraph -->

<!-- wp:group {"className":"round","style":{"spacing":{"padding":{"top":"var:preset|spacing|x-small","bottom":"var:preset|spacing|x-small","left":"var:preset|spacing|x-small","right":"var:preset|spacing|x-small"}}},"backgroundColor":"superlight-accent","layout":{"type":"constrained"}} -->
<div class="wp-block-group round has-superlight-accent-background-color has-background" style="padding-top:var(--wp--preset--spacing--x-small);padding-right:var(--wp--preset--spacing--x-small);padding-bottom:var(--wp--preset--spacing--x-small);padding-left:var(--wp--preset--spacing--x-small)"><!-- wp:momentive/icon-block {"iconId":"bx-bulb","shape":"none","backgroundColor":"none","iconColor":"secondary","className":"inline-block"} /-->
<!-- wp:paragraph {"fontSize":"medium"} -->
<p class="has-medium-font-size"><strong>Did you know?</strong></p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>Kristen is an urban gardener, a cheesemaker, and a mentor with Better Decisions, a non-profit organization equipping incarcerated and at-risk women with decision-making strategies shared through curriculum and one-on-one counseling. She lives with her family in Nashville, Tennessee, and frequently can be found attending a show at one of the city’s live music venues.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->',
    ),
    array(
        'name' => 'Tawny Kotchko',
        'slug' => 'tawny-kotchko',
        'job_position' => 'Senior Vice President, Corporate Marketing',
        'linkedin_url' => 'https://www.linkedin.com/in/tawny-kotchko/',
        'first_name' => 'Tawny',
        'last_name' => 'Kotchko',
        'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2025/10/Tawny-Kotchko.png',
        'content' => '<!-- wp:paragraph -->
<p>Tawny is a strategic marketing leader with deep expertise in driving demand generation, brand growth, and cross-functional collaboration across organizations. With a proven track record of building high-impact marketing programs, Tawny specializes in accelerating marketing’s contribution to revenue, expanding market presence, and strengthening client engagement.&nbsp;</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>As Momentive’s SVP of Corporate Marketing, Tawny leads the company’s global marketing initiatives spanning demand generation, communications, digital strategy, events, and brand – with a focus on measurable business impact and exceptional client outcomes. </p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Known for her collaborative leadership style, Tawny fosters strong relationships with internal teams and external stakeholders, ensuring that marketing is a true growth driver for the business. Passionate about innovation and results, Tawny brings creativity, analytical insight, and a client-first mindset to every initiative. </p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Prior to joining Momentive, Tawny led marketing teams at Axiom, Gartner and The New York Times. Tawny holds a BBA in marketing from Loyola University Maryland.&nbsp;&nbsp;</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p></p>
<!-- /wp:paragraph -->

<!-- wp:group {"className":"round","style":{"spacing":{"padding":{"top":"var:preset|spacing|x-small","bottom":"var:preset|spacing|x-small","left":"var:preset|spacing|x-small","right":"var:preset|spacing|x-small"}}},"backgroundColor":"superlight-accent","layout":{"type":"constrained"}} -->
<div class="wp-block-group round has-superlight-accent-background-color has-background" style="padding-top:var(--wp--preset--spacing--x-small);padding-right:var(--wp--preset--spacing--x-small);padding-bottom:var(--wp--preset--spacing--x-small);padding-left:var(--wp--preset--spacing--x-small)"><!-- wp:momentive/icon-block {"iconId":"bx-bulb","shape":"none","backgroundColor":"none","iconColor":"secondary","className":"inline-block"} /-->
<!-- wp:paragraph {"fontSize":"medium"} -->
<p class="has-medium-font-size"><strong>Did you know?</strong></p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>Tawny lives in Darien, Connecticut where she appreciates the charm of living somewhere with all four seasons and can be usually found outdoors gardening, playing tennis, or skiing. Passionate about education, she leads the PTA’s social media and communication efforts at her children’s school.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->',
    ),
    array(
        'name' => 'Adam Trenkle',
        'slug' => 'adam-trenkle',
        'job_position' => 'Chief Revenue Officer',
        'linkedin_url' => 'https://www.linkedin.com/in/adam-trenkle/',
        'first_name' => 'Adam',
        'last_name' => 'Trenkle',
        'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2025/11/Adam-Trenkle.png',
        'content' => '<!-- wp:paragraph -->
<p>Adam is an experienced sales leader who has spent his entire professional career in Southern California’s technology landscape. Throughout his career, Adam has developed a strong track record in&nbsp;scaling organizations, managing&nbsp;large, diverse technology portfolios, and driving adoption across a broad array of industries. He has led entire sales teams across the&nbsp;Americas&nbsp;and&nbsp;Europe, and held fully global, cross-functional leadership roles that have shaped his wide-ranging perspective.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>As Momentive’s Chief Revenue Officer, Adam leads a high-performing sales team and is focused on accelerating growth and increasing client acquisition, retention, and expansion.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Prior to joining Momentive,&nbsp;Adam&nbsp;spent&nbsp;16 years in various senior leadership roles at&nbsp;Ansys, part of Synopsys,&nbsp;including Vice President of Sales for Americas, Europe, and the Middle East.&nbsp;Most recently,&nbsp;Adam&nbsp;served as Corporate Vice President, Worldwide Field Sales at Cadance Design Systems.&nbsp;He holds a BSME&nbsp;in&nbsp;Mechanical Engineering from the University of Massachusetts Amherst.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p></p>
<!-- /wp:paragraph -->

<!-- wp:group {"className":"round","style":{"spacing":{"padding":{"top":"var:preset|spacing|x-small","bottom":"var:preset|spacing|x-small","left":"var:preset|spacing|x-small","right":"var:preset|spacing|x-small"}}},"backgroundColor":"superlight-accent","layout":{"type":"constrained"}} -->
<div class="wp-block-group round has-superlight-accent-background-color has-background" style="padding-top:var(--wp--preset--spacing--x-small);padding-right:var(--wp--preset--spacing--x-small);padding-bottom:var(--wp--preset--spacing--x-small);padding-left:var(--wp--preset--spacing--x-small)"><!-- wp:momentive/icon-block {"iconId":"bx-bulb","shape":"none","backgroundColor":"none","iconColor":"secondary","className":"inline-block"} /-->
<!-- wp:paragraph {"fontSize":"medium"} -->
<p class="has-medium-font-size"><strong>Did you know?</strong></p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>Adam is originally from Maine and is married with two young boys. When he’s not working, he enjoys spending time with his family and staying active through swimming, biking, and running.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->',
    ),
    array(
        'name' => 'Kathy Marshall',
        'slug' => 'kathy-marshall',
        'job_position' => 'Chief Human Resources Officer',
        'linkedin_url' => 'https://www.linkedin.com/in/kpallisermarsh/',
        'first_name' => 'Kathy',
        'last_name' => 'Marshall',
        'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2026/01/Kathy-Marshall-headshot.png',
        'content' => '<!-- wp:paragraph -->
<p>Kathy is a seasoned people and culture executive with more than 20 years of experience leading global HR organizations across high-growth technology, private equity–backed, venture-backed, and public companies. Throughout her career, she has built and scaled people strategies that drive business performance, strengthen culture, and enable sustainable growth. Her expertise spans global HR operations, talent acquisition, leadership development, M&amp;A integration, and organizational transformation, with a consistent focus on aligning people strategy to business outcomes.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>As Momentive’s Chief Human Resources Officer, Kathy leads the company’s people function for a global, remote-first workforce. She is responsible for shaping culture, improving engagement and retention, and building scalable HR infrastructure that supports growth.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Prior to joining Momentive, Kathy held leadership roles at Personify, Rev.com, Spiceworks, Blackbaud, Clear Channel (iHeartMedia), GE Energy Management, Entegris (ATMI), and Dell. Across these organizations, she partnered closely with executive teams to modernize HR operations, prepare companies for IPO and M&amp;A activity, launch global offices, and develop high-performing leadership teams. She has worked extensively across Engineering, Product, Sales, Marketing, Client Success, Finance, IT, and Professional Services, bringing a pragmatic, data-driven approach to people leadership.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Kathy holds a master’s degree in human resource management from St. Edward’s University and a bachelor’s degree in political science from Sonoma State University. She is a Certified Coach through the Center for Creative Leadership and is recognized for building inclusive, high-impact cultures that enable both people and businesses to thrive.</p>
<!-- /wp:paragraph -->

<!-- wp:group {"className":"round","style":{"spacing":{"padding":{"top":"var:preset|spacing|x-small","bottom":"var:preset|spacing|x-small","left":"var:preset|spacing|x-small","right":"var:preset|spacing|x-small"}}},"backgroundColor":"superlight-accent","layout":{"type":"constrained"}} -->
<div class="wp-block-group round has-superlight-accent-background-color has-background" style="padding-top:var(--wp--preset--spacing--x-small);padding-right:var(--wp--preset--spacing--x-small);padding-bottom:var(--wp--preset--spacing--x-small);padding-left:var(--wp--preset--spacing--x-small)"><!-- wp:momentive/icon-block {"iconId":"bx-bulb","shape":"none","backgroundColor":"none","iconColor":"secondary","className":"inline-block"} /-->
<!-- wp:paragraph {"fontSize":"medium"} -->
<p class="has-medium-font-size"><strong>Did you know?</strong></p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>Kathy lives in Bend, Oregon with her twin 15-year-olds. Born in New Zealand, she moved eight times before high school and credits that experience with her love of travel and thriving in change. Outside of work, she enjoys hiking, pickleball, and bringing positivity to everything she does.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->',
    ),
    array(
        'name' => 'Summit Roshan',
        'slug' => 'summit-roshan',
        'job_position' => 'Chief Financial Officer',
        'linkedin_url' => 'https://www.linkedin.com/in/summitroshan/',
        'first_name' => 'Summit',
        'last_name' => 'Roshan',
        'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2026/01/Summit-Roshan-3.png',
        'content' => '<!-- wp:paragraph -->
<p>Summit is a tenured finance executive with deep experience driving financial transformation, operational excellence, and scalable performance in high-growth software organizations. He is known for a collaborative, data-driven approach to financial leadership, and for building strong finance teams that drive business impact.&nbsp;</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>As Momentive’s Chief Financial Officer, he brings extensive strategic finance and operational leadership to support the company’s next phase of growth and value creation. Summit is passionate about mentoring talent, enabling cross-functional alignment, and fostering a culture of accountability and continuous improvement.&nbsp;</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Prior to joining the company, Summit served as Chief Financial Officer at Personify, where he led financial strategy, planning, and reporting to enable sustainable growth and disciplined execution. Previously, Summit held senior finance and operations leadership roles at Eptura, Anchore, and LogicMonitor, where he partnered with executive teams on strategic planning, forecasting, M&amp;A, and performance management. His earlier experience includes progressive finance leadership positions across software, technology services, and capital markets.&nbsp;&nbsp;&nbsp;</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Summit holds a master\'s degree from Tulane University and a Bachelor of Arts in Classical Studies, Ancient Greek from the University of California, Davis.</p>
<!-- /wp:paragraph -->

<!-- wp:group {"className":"round","style":{"spacing":{"padding":{"top":"var:preset|spacing|x-small","bottom":"var:preset|spacing|x-small","left":"var:preset|spacing|x-small","right":"var:preset|spacing|x-small"}}},"backgroundColor":"superlight-accent","layout":{"type":"constrained"}} -->
<div class="wp-block-group round has-superlight-accent-background-color has-background" style="padding-top:var(--wp--preset--spacing--x-small);padding-right:var(--wp--preset--spacing--x-small);padding-bottom:var(--wp--preset--spacing--x-small);padding-left:var(--wp--preset--spacing--x-small)"><!-- wp:momentive/icon-block {"iconId":"bx-bulb","shape":"none","backgroundColor":"none","iconColor":"secondary","className":"inline-block"} /-->
<!-- wp:paragraph {"fontSize":"medium"} -->
<p class="has-medium-font-size"><strong>Did you know?</strong></p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>Before pursuing a career in finance, Summit was an aspiring Classicist. In his free time, he enjoys a round of golf and has discovered the key to playing more golf is to get his four children hooked on the game, too.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->',
    ),
    array(
        'name' => 'Ravi Venkatesan',
        'slug' => 'ravi-venkatesan',
        'job_position' => 'Chief Executive Officer',
        'linkedin_url' => '',
        'first_name' => 'Ravi',
        'last_name' => 'Venkatesan',
        'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2026/05/Ravi-Headshot-Transparent.png',
        'content' => '<!-- wp:paragraph -->
<p>Ravi is a global business leader with more than 25 years of management experience across software and payments. He brings a unique combination of strategic capabilities and keen operational skills that he uses to deliver value to customers, investors, and colleagues.&nbsp;</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>As Momentive\'s CEO, Ravi is focused on accelerating the company’s growth, leading the executive leadership team, and serving on its Board of Directors.&nbsp; Ravi especially values the opportunity his role provides him to partner with mission-driven organizations, where he can help support clients and amplify their impact.&nbsp;&nbsp;</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Prior to joining Momentive, Ravi served as CEO of Cantaloupe Inc., a publicly traded digital payments and software services company, acquired by 365 Retail Markets under his leadership.&nbsp;&nbsp;</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Ravi studied Electronics at Bangalore University and completed a post-graduate program in Finance and Information Management at the Management Development Institute.</p>
<!-- /wp:paragraph -->

<!-- wp:group {"className":"round","style":{"spacing":{"padding":{"top":"var:preset|spacing|x-small","bottom":"var:preset|spacing|x-small","left":"var:preset|spacing|x-small","right":"var:preset|spacing|x-small"}}},"backgroundColor":"superlight-accent","layout":{"type":"constrained"}} -->
<div class="wp-block-group round has-superlight-accent-background-color has-background" style="padding-top:var(--wp--preset--spacing--x-small);padding-right:var(--wp--preset--spacing--x-small);padding-bottom:var(--wp--preset--spacing--x-small);padding-left:var(--wp--preset--spacing--x-small)"><!-- wp:momentive/icon-block {"iconId":"bx-bulb","shape":"none","backgroundColor":"none","iconColor":"secondary","className":"inline-block"} /-->
<!-- wp:paragraph {"fontSize":"medium"} -->
<p class="has-medium-font-size"><strong>Did you know?</strong></p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>Ravi has been a Meditation Trainer with the Heartfulness Institute for more than 20 years. He enjoys sharing his experience with meditation and engaged listening with others and believes developing these skills can transform people’s leadership and impact.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->',
    ),
);

$created = 0; $merged = 0; $photos = 0; $errors = 0;

foreach ( $leaders as $L ) {
	$name = msw_clean( $L['name'] );  // defensive: never let CDATA reach a title
	$existing_id = msw_find_person( $name );

	if ( $existing_id ) {
		$merged++;
		WP_CLI::log( sprintf( '[merge] %s -> #%d (add leader role, overwrite content)', $name, $existing_id ) );
		if ( ! $DRY ) {
			wp_set_object_terms( $existing_id, 'leader', 'person_role', true );
			wp_update_post( array( 'ID' => $existing_id, 'post_content' => $L['content'] ) );
			foreach ( array(
				'job_position' => $L['job_position'],
				'linkedin_url' => $L['linkedin_url'],
				'first_name'   => msw_clean( $L['first_name'] ),
				'last_name'    => msw_clean( $L['last_name'] ),
			) as $field => $val ) {
				if ( $val !== '' && empty( get_field( $field, $existing_id ) ) ) {
					update_field( $field, $val, $existing_id );
				}
			}
		}
		$target_id = $existing_id;
	} else {
		$created++;
		WP_CLI::log( sprintf( '[create] %s', $name ) );
		if ( $DRY ) { continue; }
		$target_id = wp_insert_post( array(
			'post_type' => 'people', 'post_status' => 'publish',
			'post_title' => $name, 'post_name' => $L['slug'],
			'post_content' => $L['content'],
		), true );
		if ( is_wp_error( $target_id ) ) {
			$errors++;
			WP_CLI::warning( 'Create failed for ' . $name . ': ' . $target_id->get_error_message() );
			continue;
		}
		wp_set_object_terms( $target_id, 'leader', 'person_role', true );
		foreach ( array(
			'job_position' => $L['job_position'],
			'linkedin_url' => $L['linkedin_url'],
			'first_name'   => msw_clean( $L['first_name'] ),
			'last_name'    => msw_clean( $L['last_name'] ),
		) as $field => $val ) {
			if ( $val !== '' ) { update_field( $field, $val, $target_id ); }
		}
	}

	if ( ! $DRY && $L['photo_url'] !== '' && ! has_post_thumbnail( $target_id ) ) {
		$att = msw_sideload_unique( $L['photo_url'], $target_id );
		if ( is_wp_error( $att ) ) {
			$errors++;
			WP_CLI::warning( 'Photo failed for ' . $name . ': ' . $att->get_error_message() );
		} else {
			set_post_thumbnail( $target_id, $att );
			$photos++;
		}
	}
}

WP_CLI::success( sprintf(
	'%s Created: %d | Merged: %d | Photos set: %d | Errors: %d',
	$DRY ? '[DRY RUN]' : 'Done.', $created, $merged, $photos, $errors
) );
