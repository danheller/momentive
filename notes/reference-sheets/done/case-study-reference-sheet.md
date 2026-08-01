# Case Study Rebuild — Reference Sheet (6 coverage posts)

Decoded, Word-artifact-cleaned content for the six posts that together exercise every field and edge case across all 151 published case studies. Build these by hand in order; by #6 you'll have defined where every field lands, and the migration script's output format will be fully specified.

All body text has been run through the same Word-artifact stripper the migration will use — removing `TextRun`/`NormalTextRun` spans, `data-contrast`, `data-ccp-props`, `xml:lang`, `class` attributes, and empty `<p>` tags. Real anchor links are preserved. Legacy attachment IDs (logo/hero) are from the old site and will need sideloading or remapping during migration.

---

## Field → destination map (applies to all posts)

| Legacy field | Becomes | Notes |
|---|---|---|
| post `<title>` | Post title | Often "Org: Product Success Story" — decide H1 vs `organization_name` |
| `organization_name` | ACF text / testimonial org | Cleaner than title for display |
| category terms | Native category panel (multi-select) | Children of Solutions parent |
| `case_study_logo` | ACF image (org logo) | Legacy attachment ID |
| `hero_image` | Featured image / ACF image | Legacy attachment ID |
| `intro_header` | Main section heading | Only 16/151 populated |
| `intro_text` | Body — intro | Word artifacts |
| `challenge_solution_text` | Body — challenge/solution | Word artifacts |
| `results_text` | Body — results | Word artifacts |
| `about_text` | About pattern | Contains org link |
| `additional_content` | Body — optional | 20/151 |
| `case_study_data` | Stats block (`data_title` / `data_description`) | 0–3 items |
| `case_study_features` | Features pattern | 0–10 items |
| `case_study_products_used` | Products relationship | CCT IDs → product posts by name |
| `case_study_author_*` + `short_quote` | momentive/testimonial block | photo only 63/151 |
| `case_study_file` | ACF file (downloadable PDF) | 43/151; some off-site |

---

## The Electrochemical Society (ECS)

> #1 — Maximal: 4 categories, multi-product, 3 stats, author photo, intro_header, short quote. The "everything" stress test.

- **slug:** `momentive-the-electrochemical-society`
- **live URL:** https://momentivesoftware.com/case-studies/momentive-the-electrochemical-society/
- **organization_name:** The Electrochemical Society
- **Solution categories:** Association Management, Career Centers, Event Management, Learning Management
- **intro_header:** Why organizations choose Momentive Software’s connected solutions to amplify their impact
- **Products (used):** ID 19 → NetForumAMS; ID 26 → YM Careers; ID 17 → Momentive Event Management Software; ID 6 → Crowd Wisdom
- **case_study_file (PDF):** _(none)_
- **author photo present:** yes
- **legacy attachment IDs:** logo=1744, hero=2761

**Stats** (`case_study_data`):

| # | data_title | data_description |
|---|---|---|
| 1 | 2,000 | Meeting attendees added using NetForum + Momentive Event Management with no additional wait time |
| 2 | 27k | Additional non-dues revenue from YM Careers while automated marketing emails do all the work |
| 3 | 43% | Growth in revenue in exhibits and sponsorships in just two years |

**Features** (`case_study_features`):

_(none)_

**Testimonial:**

- author: Shannon Reed
- title/org: Senior Director of Engagement, The Electrochemical Society
- short_quote: “When ECS needs a new add-on or software, the first place I’m going to look is the Momentive ecosystem to support NetForum, our system of record.”

### Body content (cleaned)

**Intro:**

<p>ECS’s lean team turned challenge into opportunity by building a seamlessly integrated software ecosystem: harnessing scalable data management, driving non‑dues revenue, streamlining event check‑in, and enriching learning experiences.</p>

**Challenge / Solution:**

<p>The ECS membership department  is a small team and needed to get creative, so they leveraged NetForum’s knowledge base and the NetForum Users Group to set up efficient processes. They also needed scalability to accommodate its growth, so they moved to NetForum’s cloud option hosted on Microsoft Azure.</p>
<p>ECS needed additional funding to support programs for members, so they turned to YM Careers job board to drive non-dues revenue.</p>
<p>Long lines at event registration caused frustration for staff and attendees, so ECS added Momentive Event Management to streamline its registration process. In considering learning management software to enhance the member experience, it only made sense for ECS to choose Crowd Wisdom LMS.</p>
<p>Crucial to advancing ECS’ mission is its software ecosystem, ECS leverages Momentive solutions, choosing to integrate technology to help meet and exceed its goals.</p>
<p>Being heavily involved in NetForum’s user group gives ECS support and knowledge. Through integrating NetForum with Momentive Event Management’s Badge[on]Demand, ECS now has automated processes coupled with dedicated support to make the registration process easier and pleasant for attendees.</p>
<p>Additionally, ECS uses YM Careers job board not only to bring in non-dues revenue, but as a conversation starter, making it easy to tell organizations how partnering with ECS will grant them access to their community.</p>

**Results:**

<p>Now ECS has its software ecosystem figured out. ECS uses NetForum as the source of truth for data, easily integrating with other Momentive Software products.</p>
<p>The organization successfully accommodated a huge increase in meeting attendees with Momentive Event Management’s Badge[on]Demand integration. They processed over 5000 attendees with no wait times during their biggest event to date, an increase from 3,000 attendees.</p>
<p>ECS easily boosted non-dues revenue with YM Careers to support member programs, which requires little staff effort, just monitoring. Pleased with its Momentive solutions, ECS onboard Momentive Software’s LMS to improve the staff and constituent experience by tracking learner data and improving educational offerings.</p>

**About:**

<p>ECS, a nonprofit professional society established in 1902, advances theory and practice at the forefront of electrochemistry and solid state science and technology, and allied subjects. Their robust global membership researches innovative solutions to major challenges. ECS hosts prestigious meetings, publishes research, fosters education, and collaborates with other organizations.</p>


---

## Plimoth Patuxet Museums: MIP Accounting Success Story

> #2 — Features-heavy + zero stats. Max features (10), 0 stats (Stats block must hide), additional_content present, testimonial without author photo.

- **slug:** `accounting-plimoth-patuxet-museums`
- **live URL:** https://momentivesoftware.com/case-studies/accounting-plimoth-patuxet-museums/
- **organization_name:** Plimoth Patuxet Museums
- **Solution categories:** Accounting
- **intro_header:** _(empty — uses default heading)_
- **Products (used):** ID 13 → MIP Accounting
- **case_study_file (PDF):** _(none)_
- **author photo present:** no
- **legacy attachment IDs:** logo=4826, hero=4827

**Stats** (`case_study_data`):

_(no stats — Stats block should hide itself)_

**Features** (`case_study_features`):

- General Ledger
- box-bx-calculator
- Professional certification resources and bundles
- box-bx-pie-chart-alt-2
- Bank Reconciliation
- Encumbrances
- Purchase Orders with Encumbrances
- Data Import Export
- Additional Executive View User
- Grant Administration

**Testimonial:**

- author: Laura Reilly
- title/org: Finance Manager, Plimouth Patuxet Museums
- short_quote: “I would recommend MIP Accounting to anyone looking for effective software designed with the specific financial considerations of nonprofits in mind.”

### Body content (cleaned)

**Intro:**

<p>Plimoth Patuxet Museums has been an MIP customer for over 27 years. Through powerful personal experiences with history, Plimoth Patuxet tells the stories of the Indigenous Wampanoag community and the English colonists who created a new society – in collaboration and in conflict – in the 1600s.</p>
<p>In a typical year, Plimoth Patuxet welcomes more than 300,000 visitors annually. In 2022, the living history museum – whose exhibits include the historic Mayflower II reproduction – will celebrate its 75th anniversary</p>

**Challenge / Solution:**

<p>With growth comes challenges, and Plimoth Patuxet Museums needed a robust module-based solution in order to seamlessly organize its finances. Struggling with disconnected systems proved to be inefficient and ineffective. They had half entries, incomplete posts and a cash account that was set up as a liability. For their small staff, reducing time spent on reporting, auditing and reconciling budgets was a pressing need.</p>
<p>The museum’s finance team was also responsible for various time-sensitive reports for board meetings, monthly internal meetings and banks and census reports.</p>
<p>Juggling diverse sources of funds encompassing the museum itself, gift shops, grants, donors and various programs and special events, the museum needed an easier path to integrating and reporting on all the funds.</p>

**Results:**

<p>Plimoth Patuxet can now depend on their financial accuracy, budgeting, and capabilities to run custom reports and easily manage multiple funding and revenue streams.</p>
<p>Now over 25 years with MIP, they've traveled the path from MIP DOS to the Windows-based MIP Accounting software. Additionally, Plimoth Patuxet successfully implemented MIP module add-ons including: budget, executive view, encumbrances, purchase order, and data Import/Export to help manage the entire organization’s finances.</p>
<p>“In my role as finance manager for Plimoth Patuxet, I have been using MIP for nearly three decades,” said Laura Reilly, Finance Manager. “While I have tried accounting software from a well-known global brand, it didn’t meet our needs well and was missing the fund accounting controls I feel are needed. I would recommend MIP Accounting to anyone looking for effective software designed with the specific financial considerations of nonprofits in mind.”</p>

**About:**

<p>Through powerful personal experiences of history, <a href="https://plimoth.org/" target="_blank" rel="noopener">Plimoth Patuxet Museums</a> tell the stories of the Wampanoag people and the English colonists who created a new society – in collaboration and in conflict – in the 1600s. Major exhibits include Mayflower II, the historic Patuxet Homesite, the 17th-Century English Village, and the Plimoth Grist Mill.</p>
<p>A private, 501(c)(3) not-for-profit educational organization, the Museum is a Smithsonian Institution Affiliate and receives support from the Massachusetts Cultural Council, private foundations, corporations, and local businesses.</p>

**Additional content:**

<h3>About Momentive Software   </h3>
<p>Momentive Software amplifies the impact of over 30,000 purpose-driven organizations in over 30 countries. Mission–driven organizations and associations rely on the company’s cloud-based software and services to solve their most critical challenges: engage the people they serve, simplify operations, and grow revenue.</p>
<p>Built with reliability at the core and strategically focused on events, careers, fundraising, financials, and operations, our solutions suite is bound by a common purpose to serve the organizations that make our communities a better place to live.</p>


---

## Ewald Consulting: YourMembership Success Story

> #3 — No products + has PDF. The no-product-relationship case; case_study_file present; 2 stats; 4 features.

- **slug:** `ams-ewald-consulting`
- **live URL:** https://momentivesoftware.com/case-studies/ams-ewald-consulting/
- **organization_name:** Ewald Consulting
- **Solution categories:** Association Management
- **intro_header:** _(empty — uses default heading)_
- **Products (used):** _(none)_
- **case_study_file (PDF):** https://go.yourmembership.com/hubfs/005-AMS/YM%20AMS/YM-Case%20Study/CST-YM-Ewald.pdf
- **author photo present:** no
- **legacy attachment IDs:** logo=5904, hero=5903

**Stats** (`case_study_data`):

| # | data_title | data_description |
|---|---|---|
| 1 | 135% | client growth |
| 2 | 0% | staff increase |

**Features** (`case_study_features`):

- Membership management
- box-bx-money
- Website management
- box-bxs-calendar-event

**Testimonial:**

- author: Kathie Pugaczewski, CAE, CMP
- title/org: Vice President of Communications & Technology, Ewald Consulting
- short_quote: “Working with YourMembership allows us to focus and expand our efforts to help drive deeper member engagement and increase member retention and growth.”

### Body content (cleaned)

**Intro:**

<p>Ewald Consulting migrated the entire client list to a single membership management software, YourMembership, enabling cross-training of staff on a single solution.</p>

**Challenge / Solution:**

<p>Ewald wanted to grow its business but needed to streamline its technology processes to manage an increase in clients successfully.</p>
<p>In 2005, Ewald Consulting supported 20 association clients using an assortment of technology products to manage day-to-day operations, but their workforce was at capacity. After switching to YourMembership, Ewald more than doubled their business.</p>

**Results:**

<p>After switching to YourMembership, Ewald more than doubled its business, expanding from 20 associations to 47, without increasing its internal staff. The new membership management software enabled Ewald to offer a dynamic website interface with valuable content, streamlined event management, multi-level membership invoicing, and more.</p>

**About:**

<p><a href="https://www.ewald.com/" target="_blank" rel="noopener">Ewald Consulting</a> is an association management company founded in 1982. It provides management and public relations support to state, national and international organizations.</p>


---

## United Way of Salt Lake: GiveSmart Success Story

> #4 — GiveSmart + no testimonial. Off-site brand AND testimonial-absent layout together; no features.

- **slug:** `fundraising-united-way-of-salt-lake`
- **live URL:** https://momentivesoftware.com/case-studies/fundraising-united-way-of-salt-lake/
- **organization_name:** United Way of Salt Lake
- **Solution categories:** Fundraising
- **intro_header:** _(empty — uses default heading)_
- **Products (used):** ID 9 → GiveSmart
- **case_study_file (PDF):** _(none)_
- **author photo present:** no
- **legacy attachment IDs:** logo=8134, hero=8133

**Stats** (`case_study_data`):

| # | data_title | data_description |
|---|---|---|
| 1 | $61,355 | dollars raised |
| 2 | 4,240 | donors |

**Features** (`case_study_features`):

_(none)_

**Testimonial:** _(none — testimonial block omitted)_

### Body content (cleaned)

**Intro:**

<p>United Way of Salt Lake needed to enrich its relationship with its Young Leaders, a group of young philanthropic professionals committed to reducing poverty and focusing on educating and empowering youth. Traditional forms of communication didn’t seem to resonate with the group, and participation was lagging. This led them to look for a digital solution to mobilize the Young Leaders.</p>

**Challenge / Solution:**

<p>With the help of GiveSmart, United Way implemented a text subscription campaign targeted at young professionals. They were able to upload a list of cellphone numbers, verify the numbers, and send out a subscription campaign opt-in message. The subscription campaign allowed members to learn about events, learn about volunteer opportunities, and sign-up to participate via short link in the text message. New members could opt-in at any time by sending a keyword to a 51555, and they too would be subscribed for the Young Leaders updates.</p>

**Results:**

<p>As expected, results followed. Text messaging allowed United Way to interact with the group members through their preferred method of communication and thus improved engagement and participation. The response speed to volunteer and event sign-ups drastically increased, and the unsubscribe rate remained incredibly low at 4%. To date, United Way of Salt Lake has 2,025 subscribers and maintains an active group of Young Leaders.</p>

**About:**

<p>Established in 1904 as the Salt Lake Charity Association, its original mission was to help the poor, discourage panhandling, and coordinate multiple programs. The historic “community chest” with a broad charitable mission has transformed into an agent for social change. Today, <a href="https://uw.org/">United Way of Salt Lake</a> is pursuing lasting social change on some of the toughest challenges we face, including poverty, poor health and lagging educational achievement.</p>


---

## The Consumer Attorneys Association of Los Angeles Society (CAALA): Momentive Event Management Success Story

> #5 — Single stat. The rare stats:1 case; second intro_header example; 2 features.

- **slug:** `events-the-consumer-attorneys-association-of-los-angeles-society`
- **live URL:** https://momentivesoftware.com/case-studies/events-the-consumer-attorneys-association-of-los-angeles-society/
- **organization_name:** The Consumer Attorneys Association of Los Angeles (CAALA)
- **Solution categories:** Event Management
- **intro_header:** Manage badge printing directly out of your arms
- **Products (used):** ID 17 → Momentive Event Management Software
- **case_study_file (PDF):** _(none)_
- **author photo present:** no
- **legacy attachment IDs:** logo=2729, hero=2728

**Stats** (`case_study_data`):

| # | data_title | data_description |
|---|---|---|
| 1 | 4,000+ | members |

**Features** (`case_study_features`):

- box-bxs-user-badge
- ExpressPass™ Check-in

**Testimonial:**

- author: Bill Smith
- title/org: Director of Finance & Operations, Consumer Attorneys Association of Los Angeles
- short_quote: “We had about 75 to 80 percent of our attendees bring their confirmation so they were able to scan and get their badge... I was really stressing out about Saturday because I knew it was sink or swim and every one of us were absolutely thrilled with how well it worked.”
- full testimonial: Using the badge[on]demand™ system on Saturday was phenomenal. We had about 75 to 80 percent of our attendees bring their confirmation so they were able to scan and get their badge. We had people bring paper, cell phone, iPads, big print, little print, a little bit of everything. I was really stressing out about Saturday because I knew it was sink or swim and every one of us were absolutely thrilled with how well it worked.

### Body content (cleaned)

**Intro:**

<p>The Consumer Attorneys Association of Los Angeles’ Annual Las Vegas Convention was held September 6-9. CAALA Vegas is the “largest convention of trial attorneys in the nation. It features three and a half days of educational sessions presented by the nation’s most accomplished trial lawyers, jurists and legal consultants.</p>

**Challenge / Solution:**

<p>CAALA started using Expo Logic’s badge[on]demand product in April 2012, allowing them to manage all registrations and badge prints directly out of their AMS, netFORUM by Momentive Software.</p>

**Results:**

<p>This was CAALA’s second show using Momentive Event Management’s badge[on]demand and ExpressPass™ check-in, but the first with a large number of attendees and exhibitors.</p>

**About:**

<p>The <a href="https://www.caala.org/">Consumer Attorneys Association of Los Angeles (CAALA)</a> is the nation's largest local association of plaintiffs' attorneys.</p>
<p>Consumer attorneys protect people from unsafe products, unsafe medicine, unfair business practices and unscrupulous and negligent corporate conduct. Through education and training, consumer attorneys subscribe to the highest standards of quality legal representation and ethical conduct. As attorneys who solely represent the interests of consumers, our association is a powerful advocate for victim's rights and equal access to justice.</p>
<p><a href="https://www.caala.org/">CAALA</a> educates, connects, assists and advocates for its members and has over 4,000 members.</p>


---

## Veterinary Emergency and Critical Care Society: YM Careers Success Story

> #6 — No category + author photo + PDF. Uncategorized fallback; closes last gap; 5 features.

- **slug:** `ams-veterinary-emergency-and-critical-care-society`
- **live URL:** https://momentivesoftware.com/case-studies/ams-veterinary-emergency-and-critical-care-society/
- **organization_name:** Veterinary Emergency and Critical Care Society (VECCS)
- **Solution categories:** _(none — uncategorized)_
- **intro_header:** _(empty — uses default heading)_
- **Products (used):** ID 26 → YM Careers
- **case_study_file (PDF):** https://ymcareers.momentivesoftware.com/hubfs/014%20YM%20Careers/YMC-Case%20Study/CST-YMC-VECCS.pdf
- **author photo present:** yes
- **legacy attachment IDs:** logo=7142, hero=7143

**Stats** (`case_study_data`):

| # | data_title | data_description |
|---|---|---|
| 1 | 300% | increase in revenue |
| 2 | _(blank)_ | Increased member value and engagement |
| 3 | _(blank)_ | Ability to fund valuable member programs |

**Features** (`case_study_features`):

- World-class member career development destination on a modern software platform
- Dedicated sales support to drive employer signups and revenue
- Expert marketing strategy support to drive member engagement
- New revenue-driving products, including Job Flash™ emails and recruitment guides
- New member career planning resources, like the Association Placement Service and Career Benchmark Dashboards

**Testimonial:**

- author: Lauren San Martin
- title/org: Marketing and Membership Director, VECCS
- short_quote: “The solution helps us make our career center a better offering for our members while driving more non-dues revenue from it – all without extra work on our part.”

### Body content (cleaned)

**Intro:**

<p>The Veterinary Emergency and Critical Care Society (VECCS) moved from using their in-house online job board to partnering with Momentive for turnkey job board software and career services. Our team demonstrated to VECCS how their association could provide members with an invaluable career resource while generating additional revenue. After a smooth rollout of their new online career center, VECCS has increased member engagement, boosted revenue by 300%, and continued to provide added career resources for members – all with no added work for VECCS staff.</p>

**Challenge / Solution:**

<p>VECCS wanted to upgrade their job board to provide a better member online career center while generating more revenue. As the veterinary industry faces skyrocketing demand and a shortage of veterinarians, VECCS members needed a reliable career resource.</p>
<p>With a small staff, VECCS also needed a partner to manage all aspects of sales and marketing. To achieve their goals, they implemented Momentive's job board software and career services.</p>
<p>With Momentive’s customer marketing and sales teams, VECCS now provides members with email alerts, recruitment guides, and more to help members grow their careers. VECCS utilizes the Association Placement Service to provide members with personal recruiters who find them their dream jobs. And, through Career Benchmark Dashboards, VECCS offers members and employers unprecedented career and industry insights, enabling them to make informed career decisions and become employers of choice.</p>

**Results:**

<p>In addition to increasing member value and engagement, VECCS has seen a 300% increase in revenue. This revenue boost for the small staff association has provided VECCS with a crucial replenishment of resources, all while offering its members a true career destination, especially in times of need.</p>

<p>VECCS continues to embrace new ideas for improving member value and driving revenue. Now, the small staff relies on our outstanding customer success team to handle all of the details. Over the years, our team has worked closely with VECCS to create new member offerings, such as Career Benchmark Dashboards and the Association Placement Service, to provide even more benefits for members and solidify their career center as the ultimate career destination for veterinary industry professionals.</p>

**About:**

<p>Founded in 1974, <a href="https://veccs.org/">the Veterinary Emergency and Critical Care Society (VECCS)</a> promotes the advancement of knowledge and high standards of practice in veterinary emergency medicine and critical patient care. VECCS serves about 6,000 members.</p>


---


---

## Notes discovered during decode

**`case_study_features` is icon + label pairs, not a flat list.** The repeater interleaves a text label with a Boxicons identifier (e.g. `box-bx-calculator`, `box-bx-pie-chart-alt-2`). So the Features pattern needs an icon slot per row, not just text. Decide how those legacy Boxicons map to your file-based SVG sprite system — either a small icon-name lookup, or drop icons and use text-only features. The migration script will need that decision.

**Word artifacts are in every body field.** All four WYSIWYG fields (`intro_text`, `challenge_solution_text`, `results_text`, `about_text`) carry MS-Word/Online span cruft across all 151 posts (~2,268 attribute instances). The cleaned text above is what the stripper produces; the migration applies the same pass.

**Stats can have blank `data_title`.** See VECCS (#6) — two of three stats have an empty number with only a description. The Stats block must render a description-only stat gracefully, or the migration should skip blank-number rows.

**Title vs `organization_name`.** Every post title is "Org: Product Success Story"; `organization_name` is the clean org alone. Use `organization_name` for the testimonial attribution and any logo alt; decide which drives the page H1.
