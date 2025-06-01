<?php
// announcement_data.php - Contains data for all announcements across all pages with database integration

require_once 'db.php';

/**
 * Get announcement data for a specific page
 * 
 * @param int $page_number The page number to get announcements for
 * @return array Array of announcement data for the page
 */
function getAnnouncementsForPage($page_number) {
    // Get database connection
    $host = "localhost";     
    $dbname = "Ncip_Project"; 
    $dbusername = "postgres"; 
    $dbpassword = "!*Lawrence1206"; 
    
    try {
        $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $dbusername, $dbpassword);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("SET TIME ZONE INTERVAL '+08:00'");
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        // Use fallback data if database connection fails
        return getFallbackAnnouncementsForPage($page_number);
    }
    
    // Try to get from database first
    try {
        $per_page = 3;
        $offset = ($page_number - 1) * $per_page;
        
        $query = "SELECT ANN_ID, ANN_TITLE, ANN_CONTENT, ANN_DATE 
                  FROM ANNOUNCEMENT 
                  WHERE ANN_DELETED_AT IS NULL 
                  ORDER BY ANN_DATE DESC, ANN_ID DESC 
                  LIMIT :per_page OFFSET :offset";
        
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':per_page', $per_page, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $db_announcements = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $db_announcements[] = [
                'id' => $row['ann_id'],
                'title' => $row['ann_title'],
                'url' => generateAnnouncementUrl($row['ann_id']),
                'author' => 'NCIP Cebu Provincial Office',
                'date' => formatAnnouncementDate($row['ann_date']),
                'image' => generateAnnouncementImage($row['ann_id']),
                'content' => substr($row['ann_content'], 0, 150) . '...'
            ];
        }
        
        // If we have database results, return them
        if (!empty($db_announcements)) {
            return $db_announcements;
        }
    } catch (Exception $e) {
        error_log("Database error in getAnnouncementsForPage: " . $e->getMessage());
    }
    
    // Fallback to static data if database fails or is empty
    return getFallbackAnnouncementsForPage($page_number);
}

/**
 * Get fallback announcement data for a specific page
 * 
 * @param int $page_number The page number to get announcements for
 * @return array Array of announcement data for the page
 */
function getFallbackAnnouncementsForPage($page_number) {
    $all_announcements = [
        // Page 1 announcements
        1 => [
            [
                'id' => 1,
                'title' => 'Advisory',
                'url' => 'https://ncip.gov.ph/news/advisory/',
                'author' => 'NCIP Central Office News',
                'date' => 'April 15, 2025',
                'image' => 'images/blog/advisory-539x205.jpg',
                'content' => 'On April 16, 2025, the National Commission on Indigenous Peoples – Central Office and Regional Offices will implement a work-from-home...'
            ],
            [
                'id' => 2,
                'title' => 'Makasaysayang araw para sa mga Eskaya!',
                'url' => 'https://ncip.gov.ph/news/makasaysayang-araw-para-sa-mga-eskaya/',
                'author' => 'NCIP Central Office News',
                'date' => 'March 28, 2025',
                'image' => 'images/blog/Makasaysayang-509x205.jpg',
                'content' => 'Sinaksihan ni National Commission on Indigenous Peoples Chairperson Jennifer Pia Sibug-Las ang pormal na pagkakaloob ng Certificate of Recognition of...'
            ],
            [
                'id' => 3,
                'title' => 'NCIP, nakiisa sa forum para sa kababaihan mula sa iba\'t ibang sektor',
                'url' => 'https://ncip.gov.ph/news/ncip-nakiisa-sa-forum-para-sa-kababaihan-mula-sa-ibat-ibang-sektor/',
                'author' => 'NCIP Central Office News',
                'date' => 'March 27, 2025',
                'image' => 'images/blog/kababaihan-509x205.jpg',
                'content' => 'Nakiisa ang National Commission on Indigenous Peoples (NCIP) sa 2025 Philippine Open Government Partnership (PH-OGP) National Women\'s Month Forum na...'
            ]
        ],
        // Page 2 announcements
        2 => [
            [
                'id' => 4,
                'title' => 'NCIP Dumalo sa National Women Leaders\' Summit on Halal Industry Advancement',
                'url' => 'https://ncip.gov.ph/news/ncip-dumalo-sa-national-women-leaders-summit-on-halal-industry-advancement/',
                'author' => 'NCIP Central Office News',
                'date' => 'March 25, 2025',
                'image' => 'images/blog/Dumalo-509x205.jpg',
                'content' => 'Dumalo bilang panauhin at resource speaker si NCIP Chairperson Jennifer Pia Sibug-Las sa kauna-unahang National Women Leaders\' Summit on Halal...'
            ],
            [
                'id' => 5,
                'title' => 'Workshop on the Identification of Partnership Entry Points between the Commission on Population and Development (CPD) and the National Commission on Indigenous Peoples',
                'url' => 'https://ncip.gov.ph/news/workshop-on-the-identification-of-partnership-entry-points-between-the-commission-on-population-and-development-cpd-and-the-national-commission-on-indigenous-peoples/',
                'author' => 'NCIP Central Office News',
                'date' => 'March 25, 2025',
                'image' => 'images/blog/workshop-509x205.jpg',
                'content' => 'The CPD hosted a workshop at the Sotogrande Hotel in Quezon City on March 24, 2025, inviting the NCIP to...'
            ],
            [
                'id' => 6,
                'title' => 'NCIP Chairperson Sibug-Las, Tagapanayam sa Women in Extractives Forum 2025',
                'url' => 'https://ncip.gov.ph/news/ncip-chairperson-sibug-las-tagapanayam-sa-women-in-extractives-forum-2025/',
                'author' => 'NCIP Central Office News',
                'date' => 'March 18, 2025',
                'image' => 'images/blog/tagapanayam-509-205.jpg',
                'content' => 'Nagsilbing tagapanayam si National Commission on Indigenous Peoples (NCIP) Chairperson Jennifer Pia Sibug-Las sa isang pagtitipon ng mga kababaihan sa...'
            ]
        ],
        // Page 3 announcements
        3 => [
            [
                'id' => 7,
                'title' => 'NCIP Chairperson Jennifer Pia Sibug-Las ginawaran bilang Natatanging Lingkod Bayan sa Gawad Pilipino Awards 2025',
                'url' => 'https://ncip.gov.ph/news/ncip-chairperson-jennifer-pia-sibug-las-ginawaran-bilang-natatanging-lingkod-bayan-sa-gawad-pilipino-awards-2025/',
                'author' => 'Chairperson Corner, NCIP Central Office News',
                'date' => 'March 17, 2025',
                'image' => 'images/blog/ginawaran-509x205.jpg',
                'content' => 'Ginawaran si NCIP Chairperson Jennifer Pia Sibug-Las bilang Natatanging Lingkod Bayan sa Gawad Pilipino Awards 2025 na ginanap sa AFPCOC,...'
            ],
            [
                'id' => 8,
                'title' => 'Walong Katutubong Biktima ng Human Trafficking, niligtas ng NCIP, DMW, DFA, Embahada ng Pilipinas sa Kuala Lumpur, at DSWD',
                'url' => 'https://ncip.gov.ph/news/walong-katutubong-biktima-ng-human-trafficking-niligtas-ng-ncip-dmw-dfa-embahada-ng-pilipinas-sa-kuala-lumpur-at-dswd/',
                'author' => 'NCIP Central Office News, Press Release',
                'date' => 'March 15, 2025',
                'image' => 'images/blog/walong-509x205.jpg',
                'content' => 'Ang National Commission on Indigenous Peoples (NCIP) ay mariing nagpapayo at nagpapaalala sa mga Katutubong Pamayanan na maging masigasig sa...'
            ],
            [
                'id' => 9,
                'title' => 'NCIP, Pinarangalan ng Gawad Bayanuhan sa Pamumuhunan',
                'url' => 'https://ncip.gov.ph/news/ncip-pinarangalan-ng-gawad-bayanuhan-sa-pamumuhunan/',
                'author' => 'NCIP Central Office News',
                'date' => 'March 13, 2025',
                'image' => 'images/blog/pinarangalan-509x205.jpg',
                'content' => 'Kinilala ang National Commission on Indigenous Peoples (NCIP) at si Chairperson Jennifer Pia Sibug-Las sa Gawad Bayanihan sa Pamumuhunan Awarding...'
            ]
        ],
        // Page 4 announcements
        4 => [
            [
                'id' => 10,
                'title' => 'NCIP Published its 3rd Volume of NCIP IndigeNews',
                'url' => 'https://ncip.gov.ph/news/ncip-published-its-3rd-volume-of-ncip-indigenews/',
                'author' => 'NCIP Central Office News',
                'date' => 'March 12, 2025',
                'image' => 'images/blog/published-509x205.jpg',
                'content' => 'Preserving cultures and breaking barriers. Celebrate the strength and wit of Indigenous women with us through the 3rd Volume of...'
            ],
            [
                'id' => 11,
                'title' => 'NCIP, OES Nagpulong Para sa Kapakanan ng mga Katutubo',
                'url' => 'https://ncip.gov.ph/news/ncip-oes-nagpulong-para-sa-kapakanan-ng-mga-katutubo/',
                'author' => 'NCIP Central Office News',
                'date' => 'March 11, 2025',
                'image' => 'images/blog/oes-509x205.jpg',
                'content' => 'Lunes, 10 Marso 2025, nakipagpulong ang National Commission on Indigenous Peoples (NCIP) sa Office of the Executive Secretary (OES) upang...'
            ],
            [
                'id' => 12,
                'title' => 'Philippines-United Nations (UN) Dialogue 2025: Driving Transitions for Impact, Scale, and Synergy',
                'url' => 'https://ncip.gov.ph/news/philippines-united-nations-un-dialogue-2025-driving-transitions-for-impact-scale-and-synergy/',
                'author' => 'NCIP Central Office News',
                'date' => 'March 5, 2025',
                'image' => 'images/blog/un-509x205.jpg',
                'content' => '05 March 2025 - As this year marks the 80th anniversary of the United Nations, the Philippines and the UN...'
            ]
        ],
        // Page 5 announcements
        5 => [
            [
                'id' => 13,
                'title' => 'Kababaihang Katutubo, Bida sa Women\'s Month Exhibit ng NCIP',
                'url' => 'https://ncip.gov.ph/news/kababaihang-katutubo-bida-sa-womens-month-exhibit-ng-ncip/',
                'author' => 'NCIP Central Office News',
                'date' => 'March 5, 2025',
                'image' => 'images/blog/katutubo-509x205.jpg',
                'content' => 'Nakiisa ang National Commission on Indigenous Peoples (NCIP) sa seremonyal na pagsisimula ng National Women\'s Month Celebration (NWMC) ngayong araw,...'
            ],
            [
                'id' => 14,
                'title' => 'Partnerships sealed to uphold Indigenous Rights and Environmental Stewardship',
                'url' => 'https://ncip.gov.ph/news/partnerships-sealed-to-uphold-indigenous-rights-and-environmental-stewardship/',
                'author' => 'NCIP Central Office News',
                'date' => 'March 3, 2025',
                'image' => 'images/blog/partnership-509x205.jpg',
                'content' => 'In celebration of World Wildlife Day 2025, the National Commission on Indigenous Peoples (NCIP) signed partnerships that will safeguard the...'
            ],
            [
                'id' => 15,
                'title' => 'NCIP CONDEMNS NON-MORO INDIGENOUS PEOPLES KILLINGS IN MAGUINDANAO DEL SUR',
                'url' => 'https://ncip.gov.ph/news/ncip-condemns-non-moro-indigenous-peoples-killings-in-maguindanao-del-sur/',
                'author' => 'NCIP Central Office News',
                'date' => 'February 27, 2025',
                'image' => 'images/blog/condemns-509x205.jpg',
                'content' => 'It has come to the National Commission on Indigenous Peoples\' (NCIP) knowledge that Timuay Renato Promboy, a well-known Teduray Indigenous...'
            ]
        ]
    ];
    
    return $all_announcements[$page_number] ?? [];
}

/**
 * Soft delete an announcement
 * 
 * @param int $announcement_id Announcement ID
 * @param int $user_id User ID performing the deletion
 * @return bool Success status
 */
function deleteAnnouncement($announcement_id, $user_id = null) {
    // Get database connection
    $host = "localhost";     
    $dbname = "Ncip_Project"; 
    $dbusername = "postgres"; 
    $dbpassword = "!*Lawrence1206"; 
    
    try {
        $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $dbusername, $dbpassword);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("SET TIME ZONE INTERVAL '+08:00'");
        
        $query = "UPDATE ANNOUNCEMENT 
                  SET ANN_DELETED_AT = CURRENT_TIMESTAMP, ANN_DELETED_BY = :user_id 
                  WHERE ANN_ID = :id";
        
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':id', $announcement_id, PDO::PARAM_INT);
        
        return $stmt->execute();
        
    } catch (Exception $e) {
        error_log("Error deleting announcement: " . $e->getMessage());
        return false;
    }
}

/**
 * Get total number of active (non-deleted) announcements
 * 
 * @return int Total count
 */
function getTotalActiveAnnouncements() {
    // Get database connection
    $host = "localhost";     
    $dbname = "Ncip_Project"; 
    $dbusername = "postgres"; 
    $dbpassword = "!*Lawrence1206"; 
    
    try {
        $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $dbusername, $dbpassword);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("SET TIME ZONE INTERVAL '+08:00'");
        
        $query = "SELECT COUNT(*) as total FROM ANNOUNCEMENT WHERE ANN_DELETED_AT IS NULL";
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (int)$result['total'];
        
    } catch (Exception $e) {
        error_log("Error getting total announcements: " . $e->getMessage());
        return 15; // Fallback to static count
    }
}

/**
 * Generate announcement URL based on ID
 * 
 * @param int $id Announcement ID
 * @return string URL
 */
function generateAnnouncementUrl($id) {
    $external_urls = [
        1 => 'https://ncip.gov.ph/news/advisory/',
        2 => 'https://ncip.gov.ph/news/makasaysayang-araw-para-sa-mga-eskaya/',
        3 => 'https://ncip.gov.ph/news/ncip-nakiisa-sa-forum-para-sa-kababaihan-mula-sa-ibat-ibang-sektor/',
        4 => 'https://ncip.gov.ph/news/ncip-dumalo-sa-national-women-leaders-summit-on-halal-industry-advancement/',
        5 => 'https://ncip.gov.ph/news/workshop-on-the-identification-of-partnership-entry-points-between-the-commission-on-population-and-development-cpd-and-the-national-commission-on-indigenous-peoples/',
        6 => 'https://ncip.gov.ph/news/ncip-chairperson-sibug-las-tagapanayam-sa-women-in-extractives-forum-2025/',
        7 => 'https://ncip.gov.ph/news/ncip-chairperson-jennifer-pia-sibug-las-ginawaran-bilang-natatanging-lingkod-bayan-sa-gawad-pilipino-awards-2025/',
        8 => 'https://ncip.gov.ph/news/walong-katutubong-biktima-ng-human-trafficking-niligtas-ng-ncip-dmw-dfa-embahada-ng-pilipinas-sa-kuala-lumpur-at-dswd/',
        9 => 'https://ncip.gov.ph/news/ncip-pinarangalan-ng-gawad-bayanuhan-sa-pamumuhunan/',
        10 => 'https://ncip.gov.ph/news/ncip-published-its-3rd-volume-of-ncip-indigenews/',
        11 => 'https://ncip.gov.ph/news/ncip-oes-nagpulong-para-sa-kapakanan-ng-mga-katutubo/',
        12 => 'https://ncip.gov.ph/news/philippines-united-nations-un-dialogue-2025-driving-transitions-for-impact-scale-and-synergy/',
        13 => 'https://ncip.gov.ph/news/kababaihang-katutubo-bida-sa-womens-month-exhibit-ng-ncip/',
        14 => 'https://ncip.gov.ph/news/partnerships-sealed-to-uphold-indigenous-rights-and-environmental-stewardship/',
        15 => 'https://ncip.gov.ph/news/ncip-condemns-non-moro-indigenous-peoples-killings-in-maguindanao-del-sur/'
    ];
    
    return $external_urls[$id] ?? 'announcement-detail.php?id=' . $id;
}

/**
 * Generate announcement image path based on ID
 * 
 * @param int $id Announcement ID
 * @return string Image path
 */
function generateAnnouncementImage($id) {
    $image_map = [
        1 => 'images/blog/advisory-539x205.jpg',
        2 => 'images/blog/Makasaysayang-509x205.jpg',
        3 => 'images/blog/kababaihan-509x205.jpg',
        4 => 'images/blog/Dumalo-509x205.jpg',
        5 => 'images/blog/workshop-509x205.jpg',
        6 => 'images/blog/tagapanayam-509-205.jpg',
        7 => 'images/blog/ginawaran-509x205.jpg',
        8 => 'images/blog/walong-509x205.jpg',
        9 => 'images/blog/pinarangalan-509x205.jpg',
        10 => 'images/blog/published-509x205.jpg',
        11 => 'images/blog/oes-509x205.jpg',
        12 => 'images/blog/un-509x205.jpg',
        13 => 'images/blog/katutubo-509x205.jpg',
        14 => 'images/blog/partnership-509x205.jpg',
        15 => 'images/blog/condemns-509x205.jpg'
    ];
    
    return $image_map[$id] ?? 'images/blog/default-announcement.jpg';
}

/**
 * Format announcement date
 * 
 * @param string $date Database date
 * @return string Formatted date
 */
function formatAnnouncementDate($date) {
    try {
        $datetime = new DateTime($date);
        return $datetime->format('F j, Y');
    } catch (Exception $e) {
        return date('F j, Y');
    }
}

/**
 * Render announcement HTML for a specific page
 * 
 * @param int $page_number The page number to render announcements for
 * @return string HTML for announcements
 */
function renderAnnouncementsForPage($page_number) {
    $announcements = getAnnouncementsForPage($page_number);
    $html = '';
    
    if (empty($announcements)) {
        return '<div class="alert alert-info">
                    <h4>No announcements found</h4>
                    <p>There are currently no announcements available for this page. Please check back later.</p>
                </div>';
    }
    
    foreach ($announcements as $announcement) {
        $html .= '
        <div class="post type-post status-publish format-standard category-design category-themes tag-corporate tag-minimal group blog-elegant">
            <!-- post featured & title -->
            <div class="thumbnail">
                <div class="row">
                    <!-- post meta -->
                    <div class="meta group span3">
                        <div>
                            <h2 class="post-title">
                                <a href="' . htmlspecialchars($announcement['url']) . '" target="_blank">
                                    ' . htmlspecialchars($announcement['title']) . '
                                </a>
                            </h2>

                            <p class="author">
                                <img src="images/icons/author.png" alt="icon-user"/><span>Author:</span>
                                <a href="https://ncip.gov.ph/news-category/ncip-news/" title="Posts by ' . htmlspecialchars($announcement['author']) . '" target="_blank" rel="author">
                                    ' . htmlspecialchars($announcement['author']) . '
                                </a>
                            </p>

                            <p class="date">
                                <img src="images/icons/date.png" alt="icon-calendar"/>
                                <span>Date:</span>
                                ' . htmlspecialchars($announcement['date']) . '
                            </p>
                        </div>
                        
                        <!-- Add comment section at the end of each post -->
                        <div class="comments-section" id="announcement-' . $announcement['id'] . '">
                            <h3>Comments</h3>
                            
                            <!-- Display comments for this specific announcement -->
                            <div id="comments-container-' . $announcement['id'] . '" class="comments-container">';
                            
        // Check if renderComments function exists before calling it
        if (function_exists('renderComments')) {
            $html .= renderComments($announcement['id']);
        } else {
            $html .= '<p>Comments system loading...</p>';
        }
        
        $html .= '
                            </div>
                            
                            <!-- Add comment form for this specific announcement -->';
                            
        // Check if renderCommentForm function exists before calling it
        if (function_exists('renderCommentForm')) {
            $html .= renderCommentForm($announcement['id']);
        } else {
            $html .= '<p>Comment form loading...</p>';
        }
        
        $html .= '
                        </div>
                        
                        <div>
                            <div class="socials">
                                <h2>Share on</h2>

                                <div class="socials-square-small facebook-small square">
                                    <a href="https://www.facebook.com/CebuPO" class="socials-square-small facebook" original-title="Facebook" target="_blank">facebook</a>
                                </div>
                                <div class="socials-square-small instagram-small square">
                                    <a href="https://www.instagram.com/ncipcebupo/" class="socials-square-small instagram" original-title="Instagram" target="_blank">instagram</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- post title -->
                    <div class="the-content span6">
                        <div>
                            <img width="539" height="205" src="' . htmlspecialchars($announcement['image']) . '" class="thumbnail wp-post-image" alt="' . htmlspecialchars($announcement['title']) . '"/>
                            <!-- post content -->
                            <div class="the-content">
                                <p>' . htmlspecialchars($announcement['content']) . '</p>

                                <p>
                                    <a href="' . htmlspecialchars($announcement['url']) . '" target="_blank" class="not-btn more-link" data-announcement-id="' . $announcement['id'] . '">
                                        Read more <i class="icon-external-link"></i>
                                    </a>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>';
    }
    
    return $html;
}
?>