<?php
/**
 * Migrate webinar presenters into the People CPT.
 *
 * Run with:  wp eval-file migrate-presenters.php
 *   add --dry-run as a trailing arg to preview without writing:
 *            wp eval-file migrate-presenters.php dry
 *
 * What it does, per presenter:
 *   1. If a People post with the same name exists (e.g. an already-converted
 *      author), reuse it: add the "presenter" role, and fill job_position only
 *      if empty. If the canonical name carries a credential (e.g. "Tirrah
 *      Switzer, CAE"), the existing post title is upgraded to include it.
 *   2. Otherwise create a new People post with role "presenter".
 *   3. Sideload the presenter photo from the live site as the featured image,
 *      only if the post has no thumbnail yet. Images are de-duplicated by
 *      source URL so re-runs don't re-download.
 *
 * Idempotent: safe to run multiple times.
 */

$DRY = in_array( 'dry', $args ?? array(), true ) || ( isset( $argv ) && in_array( 'dry', $argv, true ) );

if ( ! function_exists( 'media_sideload_image' ) ) {
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
}

// Ensure the "presenter" role term exists.
if ( ! term_exists( 'presenter', 'person_role' ) ) {
	if ( ! $DRY ) {
		wp_insert_term( 'Presenter', 'person_role', array( 'slug' => 'presenter' ) );
	}
	WP_CLI::log( '[term] ensured person_role: presenter' );
}

/**
 * Find an existing People post by exact title, OR by base name (title without a
 * trailing credential). Returns post ID or 0.
 */
function msw_find_person( $name ) {
	$base = trim( preg_split( '/,/', $name )[0] );

	// Try exact title first.
	$q = get_posts( array(
		'post_type'      => 'people',
		'post_status'    => 'any',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'title'          => $name,
	) );
	if ( $q ) {
		return $q[0];
	}

	// Fall back to base-name match against existing titles (handles author
	// posts stored without the credential).
	$all = get_posts( array(
		'post_type'      => 'people',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	) );
	foreach ( $all as $pid ) {
		$existing_base = trim( preg_split( '/,/', get_the_title( $pid ) )[0] );
		if ( strcasecmp( $existing_base, $base ) === 0 ) {
			return $pid;
		}
	}
	return 0;
}

/**
 * Sideload an image URL and return the new attachment ID, reusing a prior
 * import of the same source URL if present (tracked via _source_url meta).
 */
function msw_sideload_unique( $url, $post_id ) {
	$existing = get_posts( array(
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_key'       => '_msw_source_url',
		'meta_value'     => $url,
	) );
	if ( $existing ) {
		return $existing[0];
	}

	$tmp = download_url( $url );
	if ( is_wp_error( $tmp ) ) {
		return $tmp;
	}
	$file_array = array(
		'name'     => basename( parse_url( $url, PHP_URL_PATH ) ),
		'tmp_name' => $tmp,
	);
	$att_id = media_handle_sideload( $file_array, $post_id );
	if ( is_wp_error( $att_id ) ) {
		@unlink( $tmp );
		return $att_id;
	}
	update_post_meta( $att_id, '_msw_source_url', $url );
	return $att_id;
}

$people = array(
    array('name' => 'Adam Hostetter', 'job_position' => 'CEO, FUSE Next', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2025/09/Adam-Hostetter-Headshot.png'),
    array('name' => 'Allyson Olaniel', 'job_position' => 'Membership Expert, YourMembership AMS', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2025/08/AllysonOlaniel-300x300-1-250x250-1.png'),
    array('name' => 'Amanda Davis', 'job_position' => 'CMP Chief Experience Officer, Blue Sky eLearn', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2025/08/1734388913.9493768_Amanda-Davis-Headshot-Circle.png'),
    array('name' => 'Amber Worthen', 'job_position' => 'Founder & CEO, Email Maven', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2025/08/1742337415.283075_Amber-Worthen-Headshot-Circle.png'),
    array('name' => 'Andrea Genevieve', 'job_position' => 'Andrea Genevieve Creative', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2026/04/headshotsmall.png'),
    array('name' => 'Art Taylor', 'job_position' => 'CEO, Association of Fundraising Professionals', 'photo_url' => ''),
    array('name' => 'Ashlee Droscher', 'job_position' => 'CFRE Director of Development Radiation Oncology Institute', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2026/04/Ashlee-D_Circle-768x756-1.png'),
    array('name' => 'Brandon Lyons', 'job_position' => 'SVP of Strategic Partnerships - DonorSearch', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2026/04/Brandon-Lyons_DonorSearch.jpeg'),
    array('name' => 'Breland Mettler', 'job_position' => 'Events Expert​, Momentive Software', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2025/07/Breland-Mettler-e1751393992615.webp'),
    array('name' => 'Bria Thomas', 'job_position' => 'Associate Director, Operations and Special Projects, National Community Pharmacists Association (NCPA)', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2026/06/Bria-Thomas-headshot.jpg'),
    array('name' => 'Brian Chignoli', 'job_position' => 'VP of AI and Product Experience, Momentive Software', 'photo_url' => ''),
    array('name' => 'Cara Dickerson', 'job_position' => 'VP of Client Success, Momentive Software (Moderator)', 'photo_url' => ''),
    array('name' => 'Celeste Flores - GivingTuesday', 'job_position' => 'Director, US + Canada Hub', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2026/04/Celeste-Flores.jpg'),
    array('name' => 'Chelsey Wilson', 'job_position' => 'Senior Product Marketing Manager, Momentive Software', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2026/05/Chelsey-Wilson_circle.png'),
    array('name' => 'Chris Baiocchi', 'job_position' => 'Founder & CEO, Resolute Philanthropy', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2026/04/Chris-B_Resolute-Philanthropy.png'),
    array('name' => 'Chris Barlow', 'job_position' => 'Founder and Customer Happiness Director - Beeline Marketing', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2026/04/Chris-Barlow.png'),
    array('name' => 'Chris Capistran', 'job_position' => 'Vice President, Industry Strategy, Momentive Software', 'photo_url' => ''),
    array('name' => 'Collier Faubion', 'job_position' => 'Solutions Engineering Manager​, Momentive Software', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2025/10/Collier-Faubion-Headshot.jpg'),
    array('name' => 'Corinne Contento', 'job_position' => 'Director of Tax, Momentive Software', 'photo_url' => ''),
    array('name' => 'Dan Campbell BAS', 'job_position' => 'AMM Raising Paddles Benefit Auctions & Fundraising', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2026/04/DC.jpg'),
    array('name' => 'Dan Streeter', 'job_position' => 'Chief Executive Officer, Mission Fuel', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2025/08/Dan-Streeter-Headshot.jpeg'),
    array('name' => 'Dana Smith', 'job_position' => 'Senior Customer Success Manager GiveSmart', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2026/04/Dana-Smith.png'),
    array('name' => 'Daniel Martin', 'job_position' => 'Managing Director, Development, American Society of Landscape Architects', 'photo_url' => ''),
    array('name' => 'David Park', 'job_position' => 'Director of Data & Business Analytics, National League of Cities', 'photo_url' => ''),
    array('name' => 'Debra Lally', 'job_position' => 'CAE, Executive Director, National Association of Emergency Medical Technicians (NAEMT)', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2026/06/Deb-Lally-headshot.jpg'),
    array('name' => 'Deedee De La Cruz', 'job_position' => 'Director of Demand Generation Nonprofit Solutions', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2026/04/Deedee-De-La-Cruz-300x300-1.png'),
    array('name' => 'Doug Stopper', 'job_position' => 'VP of Cybersecurity, Risk, & Compliance, Momentive Software', 'photo_url' => ''),
    array('name' => 'Dr. Kristen Wall, MA EdD', 'job_position' => 'Director of Learning & Development, Blue Sky eLearn', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2025/08/Kristen-Wall-Headshot-Circle-300x300-1.png'),
    array('name' => 'Dr. Zoe Falls', 'job_position' => 'Instructional Designer, Blue Sky eLearn', 'photo_url' => ''),
    array('name' => 'Drew Tokosch', 'job_position' => 'Account Executive, Momentive Software', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2025/07/Drew-Tokosch-headshot.webp'),
    array('name' => 'Dustin Radtke', 'job_position' => 'Chief AI Officer, Momentive Software', 'photo_url' => ''),
    array('name' => 'Elon Packin', 'job_position' => 'Head of Partnerships', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2026/04/Elon-Packin_Circle-768x762-1.png'),
    array('name' => 'Eric Newman', 'job_position' => 'VP of YM Client Success', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2026/05/Eric_Newman-150x150-1.png'),
    array('name' => 'Evan Reid', 'job_position' => 'Senior Director of Analytics, The American Speech-Language-Hearing Association (ASHA)', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2025/06/Evan-Reid.webp'),
    array('name' => 'Grace Dick', 'job_position' => 'Partnerships Manager, <a href="https://doublethedonation.com/" target="_blank">Double the Donation</a>', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2026/05/Grace-Dick-Headshot-Square.png'),
    array('name' => 'Howard Pollock', 'job_position' => 'Director of Enterprise Strategy, Momentive Software', 'photo_url' => ''),
    array('name' => 'Jasamine Riel', 'job_position' => 'Certification Products Manager, Pharmacy Technician Certification Board', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2025/07/Jasamine-Riel.jpg'),
    array('name' => 'Jennifer Bottoms, CPA', 'job_position' => '', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2025/10/Jennifer-Bottoms_Momentive-Software-headshot.png'),
    array('name' => 'Jennifer Gleason', 'job_position' => 'Senior Manager of Risk Management, YMCA of the USA', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2026/03/Jennifer-Gleason-Headshot.png'),
    array('name' => 'Jerel Noel', 'job_position' => 'Executive Director, Cardiovascular Credentialing International', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2025/07/Jerel-Noel.webp'),
    array('name' => 'Jeremy Zissman, CPA', 'job_position' => 'CFO, Fiscal Strategies 4 Nonprofits (FS4N)', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2024/11/Jeremy-Zissman_headshot-150x150-1.png'),
    array('name' => 'Jesse Shore', 'job_position' => 'Learning Programs Expert, Momentive Software', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2025/06/jesse-shore-avatar.webp'),
    array('name' => 'Jesse Shorts', 'job_position' => 'Learning Programs Expert, Momentive Software', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2025/08/JShorts-Headshot-1.jpg'),
    array('name' => 'Jessica Metzler', 'job_position' => 'Director of Operations Freestone LMS', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2025/08/JessicaMeltzer_HeadshotCircle.png'),
    array('name' => 'Jodi Ray', 'job_position' => 'Client Success Manager, Blue Sky eLearn', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2025/09/Jodi-Ray-Headshot-Circle-150x150-1.png'),
    array('name' => 'John Leh', 'job_position' => 'CEO, Talented Learning', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2024/11/1726264616.6383712_John-Leh-Headshot-Circle-350x350-1.png'),
    array('name' => 'Jordan Baker, M.Ed.', 'job_position' => 'Learning Solutions Advisor, Blue Sky eLearn', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2025/08/1716416364.3840961_Jordan-Baker-Headshot-Circle350.png'),
    array('name' => 'Joshua Crowther', 'job_position' => 'Vice President Dunham+Company', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2026/04/Joshua-Crowther.png'),
    array('name' => 'Karis Call, CPA', 'job_position' => 'Partner, Rubino & Co.', 'photo_url' => ''),
    array('name' => 'Kate McGinn', 'job_position' => 'Chief Executive Officer The Bold Stripe', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2026/04/Kate-McGinn_circle-2048x2048-1.png'),
    array('name' => 'Kathy Nothnagel', 'job_position' => 'Manager, Content Marketing - Momentive Software', 'photo_url' => ''),
    array('name' => 'Kerri McGovern, MPP, CAE, AAiP', 'job_position' => 'VP Membership, Engagement, and Volunteerism, Council for Advancement and Support of Education (CASE)', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2026/03/Kerri-McGovern-headshot.jpg'),
    array('name' => 'Kristen Wall', 'job_position' => 'VP of Learning & Training Services, Momentive Software', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2025/09/Kristen-Wall-Headshot.png'),
    array('name' => 'Kristi Allen, CPA', 'job_position' => 'VP, Finance & Administration​, NextUp', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2025/10/Kristi-Allen-Headshot.jpg'),
    array('name' => 'Liam O\'Malley, CAE, PMP', 'job_position' => 'Vice President of Association Solutions, Blue Sky eLearn', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2024/11/1726264912.311516_Liam-OMalley-Headshot-Circle-350x350-1.png'),
    array('name' => 'Liam O’Malley', 'job_position' => 'CAE, PMP', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2025/08/Liam-Headshot.webp'),
    array('name' => 'Lisa Greer', 'job_position' => 'Bestselling Author, Philanthropist, and Fundraising Coach', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2026/04/Lisa-Greer-300x300-1.png'),
    array('name' => 'Madison Kautman', 'job_position' => 'Senior Marketing Campaign Manager, Givesmart powered by Momentive Software', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2026/04/Madison_circle-1.png'),
    array('name' => 'Mark Hopwood', 'job_position' => 'Senior Director, Nonprofit Strategy, Momentive Software', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2026/03/Mark-Hopwood-Headshot.png'),
    array('name' => 'Mark Wallach, MBA', 'job_position' => 'CEO Emeritus, Mobile Engagement Strategies', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2025/08/1731107411.0269582_Mark-Wallach-Headshot-Circle-350x350-1.png'),
    array('name' => 'Mary Connor, CAE, AAiP', 'job_position' => 'Chief Strategy Officer, Stringfellow Management Group', 'photo_url' => ''),
    array('name' => 'Mary Kate Walberg', 'job_position' => 'YM Client Success Program Manager', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2026/05/Mary-Kate-YourMembership.jpg'),
    array('name' => 'Michael Gellman, CPA, CGMA', 'job_position' => 'Co-Founder, Fiscal Strategies 4 Nonprofits (FS4N) & Sustainability Education 4 Nonprofits (SE4N)', 'photo_url' => ''),
    array('name' => 'Michael Kumpf', 'job_position' => 'Director of Enterprise Sales, YM Careers', 'photo_url' => ''),
    array('name' => 'Michelle Baughman', 'job_position' => 'Manager, Exhibits and Sponsorship, America Society of Landscape Architects', 'photo_url' => ''),
    array('name' => 'Michelle Schweitz', 'job_position' => 'VP of Product Marketing, Momentive Software', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2025/08/Michelle-Schweitz.jpg'),
    array('name' => 'Mike Puffer', 'job_position' => '', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2025/10/Mike-Puffer_CB-headshot.png'),
    array('name' => 'Mike Shea', 'job_position' => 'Chief Operating Officer', 'photo_url' => ''),
    array('name' => 'Nathan Richter', 'job_position' => 'Senior Partner, Wakefield Research', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2026/03/Nathan-Richter-Square.png'),
    array('name' => 'Nicole Bowen', 'job_position' => 'CAE, Nonprofit Relationship Management, RSM US LLP', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2025/08/Nicole-Bowen.webp'),
    array('name' => 'Nikkii Kashub', 'job_position' => 'Director of Donor Management Solutions - GiveSmart', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2026/04/Nikkii-Kashub.png'),
    array('name' => 'Olivia Bass', 'job_position' => 'Solutions Engineer', 'photo_url' => ''),
    array('name' => 'Pam Loeb', 'job_position' => 'Principal, Edge Research', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2025/07/Pam-Loeb.webp'),
    array('name' => 'Patrick Curtis, CPA, CGMA', 'job_position' => 'Shareholder', 'photo_url' => ''),
    array('name' => 'Patrick Gotham', 'job_position' => 'Senior Vice President, CCS Fundraising', 'photo_url' => ''),
    array('name' => 'Paul Preziotti, CPA', 'job_position' => 'Partner, Johnson Lambert', 'photo_url' => ''),
    array('name' => 'Penny Whoolery', 'job_position' => 'Director, Certification & Membership, American Association of Cost Engineering International', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2025/07/Penny-Whoolery.webp'),
    array('name' => 'Pooya Pourak', 'job_position' => 'Co-Founder, CEO MatchNice', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2026/04/Pooya-Pourak-Headshot-copy-768x767-1.png'),
    array('name' => 'Rebecca Achurch', 'job_position' => 'Chief Executive Officer, Achurch Consulting, LLC', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2025/05/Rebecca-Achurch.webp'),
    array('name' => 'Renèe Croteau', 'job_position' => 'Senior Director, Distinguished Events, American Cancer Society', 'photo_url' => ''),
    array('name' => 'Rich Vallaster, CEM, QAS, AAiP', 'job_position' => 'Sr. Director of Industry Strategy, Momentive Software', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2026/05/Rich-Vallaster-Headshot.png'),
    array('name' => 'Rob Miller, MPA, CAE', 'job_position' => 'SVP of Association Strategy, Momentive Software', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2025/06/Rob-Miller.webp'),
    array('name' => 'Ronnie Dudek', 'job_position' => 'Director of Streaming & Production Services, Blue Sky eLearn', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2025/09/Ronnie-Dudek-Headshot-Circle.png'),
    array('name' => 'Ryan Woroniecki', 'job_position' => 'Advisor - Momentum', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2026/04/Nikkii-Kashub.png'),
    array('name' => 'Sean Connelly', 'job_position' => 'Director, Product Management, Path LMS', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2025/08/Sean-C.jpg'),
    array('name' => 'Shannon Reed', 'job_position' => 'Senior Director of Engagement, The Electrochemical Society', 'photo_url' => ''),
    array('name' => 'Stan Duncan, CPA', 'job_position' => 'Sr. Compliance Analyst, Momentive Software', 'photo_url' => ''),
    array('name' => 'Tara Kielty', 'job_position' => 'Campaign Manager GiveSmart', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2026/04/Tara-Kielty-300x300-1.png'),
    array('name' => 'Teshieka Curtis-Pugh', 'job_position' => 'Executive Director, South Carolina Nurses Association', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2026/03/Teshieka-K-Curtis-Pugh_headshot-Square.png'),
    array('name' => 'Tirrah Switzer, CAE', 'job_position' => 'Vice President, Product Marketing, Momentive Software', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2026/04/Tirrah-Switzer_circle.png'),
    array('name' => 'Tom Harlow, CPA, CAE', 'job_position' => 'Interim Chief Finance Officer at ASAE: The Center for Association Leadership', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2025/09/Tom-Harlow.png'),
    array('name' => 'Tracy Petrillo, EdD', 'job_position' => 'CAE Chief Learning Officer, CASBO', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2025/08/1734388772.4900324_Tracy-Petrillo-Headshot-Circle.png'),
    array('name' => 'Tricia Roseveare', 'job_position' => 'Founder and CEO of riskfreeitemshop.com', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2026/04/Tricia-Headshot.jpeg'),
    array('name' => 'Tyler Mosley, CPA, CFE', 'job_position' => 'Partner, Audit Department, Atchley & Associates', 'photo_url' => ''),
    array('name' => 'Wes Trochlil', 'job_position' => 'Owner, Effective Database Management', 'photo_url' => 'https://momentivesoftware.com/wp-content/uploads/2025/06/Wes-Trochlil.webp'),
);

$created = 0; $merged = 0; $photos = 0; $skipped_photo = 0; $errors = 0;

foreach ( $people as $person ) {
	$name = $person['name'];
	$job  = $person['job_position'];
	$url  = $person['photo_url'];

	$existing_id = msw_find_person( $name );

	if ( $existing_id ) {
		$merged++;
		WP_CLI::log( sprintf( '[merge] %s -> #%d', $name, $existing_id ) );

		if ( ! $DRY ) {
			// Add presenter role (append).
			wp_set_object_terms( $existing_id, 'presenter', 'person_role', true );

			// Upgrade title if our canonical name has a credential the
			// stored title lacks.
			if ( strcasecmp( get_the_title( $existing_id ), $name ) !== 0
				&& strlen( $name ) > strlen( get_the_title( $existing_id ) ) ) {
				wp_update_post( array( 'ID' => $existing_id, 'post_title' => $name ) );
			}

			// Fill job_position only if empty.
			$cur = get_field( 'job_position', $existing_id );
			if ( empty( $cur ) && $job !== '' ) {
				update_field( 'job_position', $job, $existing_id );
			}
		}
		$target_id = $existing_id;
	} else {
		$created++;
		WP_CLI::log( sprintf( '[create] %s', $name ) );

		if ( $DRY ) {
			continue;
		}
		$target_id = wp_insert_post( array(
			'post_type'   => 'people',
			'post_status' => 'publish',
			'post_title'  => $name,
		), true );
		if ( is_wp_error( $target_id ) ) {
			$errors++;
			WP_CLI::warning( 'Failed to create ' . $name . ': ' . $target_id->get_error_message() );
			continue;
		}
		wp_set_object_terms( $target_id, 'presenter', 'person_role', true );
		if ( $job !== '' ) {
			update_field( 'job_position', $job, $target_id );
		}
	}

	// Featured image: only if missing and we have a URL.
	if ( ! $DRY && $url !== '' && ! has_post_thumbnail( $target_id ) ) {
		$att = msw_sideload_unique( $url, $target_id );
		if ( is_wp_error( $att ) ) {
			$errors++;
			WP_CLI::warning( 'Photo failed for ' . $name . ': ' . $att->get_error_message() );
		} else {
			set_post_thumbnail( $target_id, $att );
			$photos++;
		}
	} elseif ( $url !== '' && has_post_thumbnail( $target_id ) ) {
		$skipped_photo++;
	}
}

WP_CLI::success( sprintf(
	'%s Created: %d | Merged: %d | Photos set: %d | Photos skipped (already had one): %d | Errors: %d',
	$DRY ? '[DRY RUN]' : 'Done.',
	$created, $merged, $photos, $skipped_photo, $errors
) );