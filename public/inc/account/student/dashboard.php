<?php
defined('ABSPATH') || die();

require_once WLSM_PLUGIN_DIR_PATH . 'includes/helpers/WLSM_M_Setting.php';
require_once WLSM_PLUGIN_DIR_PATH . 'includes/helpers/WLSM_M_Session.php';
require_once WLSM_PLUGIN_DIR_PATH . 'includes/helpers/WLSM_M_Class.php';
require_once WLSM_PLUGIN_DIR_PATH . 'includes/helpers/WLSM_Config.php';

global $wpdb;

$settings_dashboard = WLSM_M_Setting::get_settings_dashboard($school_id);
$school_enrollment_number = $settings_dashboard['school_enrollment_number'];
$school_admission_number = $settings_dashboard['school_admission_number'];

$student_name = WLSM_M_Staff_Class::get_name_text($student->student_name);
$class_school_id = $student->ID;
$class_id = $student->class_id;
$notices_per_page = WLSM_M::notices_per_page();
$notices_query = WLSM_M::notices_query($school_id);

$notices_total = $wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM ({$notices_query}) AS combined_table", $school_id));
$notices_page = isset($_GET['notices_page']) ? absint($_GET['notices_page']) : 1;
$notices_page_offset = ($notices_page * $notices_per_page) - $notices_per_page;
$notices = $wpdb->get_results($wpdb->prepare($notices_query . ' ORDER BY n.ID DESC LIMIT %d, %d', $school_id, $notices_page_offset, $notices_per_page));

$filtered_notices = array_filter($notices, function ($notice) use ($student) {
    $notice_data = @unserialize($notice->notice_data);
    // If notice_data is not an array or empty, show notice to all
    if (!is_array($notice_data) || empty($notice_data)) {
        return true;
    }
    // If classes array doesn't exist or is empty, show notice to all
    if (!isset($notice_data['classes']) || empty($notice_data['classes'])) {
        return true;
    }
    // Check if notice is for student's class or for all classes
    return in_array($student->class_id, $notice_data['classes']) || in_array('all', $notice_data['classes']);
});

$section = WLSM_M_Staff_Class::get_school_section($school_id, $student->section_id);
$class_label = $section->class_label;
$section_label = $section->label;

$attendance = WLSM_M_Staff_General::get_student_attendance_stats($student->ID);
$invoices = WLSM_M_Staff_Accountant::get_student_pending_invoices($student->ID);

$vehicle_id = $student->route_vehicle_id;
$transportation_details = [];
if ($vehicle_id) {
    $query = $wpdb->prepare(
        'SELECT ro.name, ro.fare, v.vehicle_number, v.driver_name, v.driver_phone
        FROM ' . WLSM_ROUTE_VEHICLE . ' as rov
        JOIN ' . WLSM_ROUTES . ' as ro ON ro.ID = rov.route_id
        JOIN ' . WLSM_VEHICLES . ' as v ON v.ID = rov.vehicle_id
        WHERE rov.ID = %d',
        $vehicle_id
    );
    $transportation_details = $wpdb->get_results($query);
}

$invoices = WLSM_M_Staff_Accountant::get_student_pending_invoices_paid($student->ID, 1);
$check_dashboard_display = WLSM_M_Setting::get_dash($invoices);

$schools = $wpdb->get_results($wpdb->prepare('SELECT s.ID, s.label, s.phone, s.email, s.address, s.is_active FROM ' . WLSM_SCHOOLS . ' as s WHERE s.ID = %d', $school_id));

if ($schools[0]->is_active === '0') {
    echo '<span style="color: red;"> This School is not active </span>';
    die;
}

if ($invoices && 'paid' !== $check_dashboard_display) {
    require_once WLSM_PLUGIN_DIR_PATH . 'includes/partials/pending_fee_invoices.php';
} else {
    require_once WLSM_PLUGIN_DIR_PATH . 'public/inc/account/student/partials/navigation.php'; ?>
    <div class="wlsm-content-area wlsm-section-dashboard wlsm-student-dashboard">
        <!-- Student Overview Section -->

        <div class="wlsm-grid">
            <!-- Profile Card -->
            <div class="wlsm-card wlsm-profile-card">
                <div class="wlsm-profile-header">
                    <div class="wlsm-student-photo-container">
                        <?php if ($student->photo_id) :
                            $photo_url = wp_get_attachment_url($student->photo_id);
                            if ($photo_url) : ?>
                                <img src="<?php echo esc_url($photo_url); ?>"
                                    alt="<?php echo esc_attr($student_name); ?>"
                                    class="wlsm-student-photo">
                            <?php endif; ?>
                        <?php else : ?>
                            <div class="wlsm-student-photo-default">
                                <?php echo esc_html(strtoupper(substr($student_name, 0, 1))); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <h3 class="wlsm-student-name"><?php echo esc_html($student_name); ?></h3>
                    <div class="wlsm-student-meta">
                        <span class="wlsm-badge">
                            <?php echo esc_html(WLSM_M_Class::get_label_text($student->class_label)); ?> -
                            <?php echo esc_html(WLSM_M_Class::get_label_text($student->section_label)); ?>
                        </span>
                    </div>
                </div>

                <!-- Student Details Grid -->
                <div class="wlsm-profile-body">
                    <div class="wlsm-info-grid">
                        <ul class="wlsm-st-details-list">
                            <li>
                                <span class="wlsm-st-details-list-key"><?php esc_html_e('Name'); ?>:</span>
                                <span class="wlsm-st-details-list-value"><?php echo esc_html($student_name); ?></span>
                            </li>
                            <?php if ($school_enrollment_number) : ?>
                                <li>
                                    <span class="wlsm-st-details-list-key"><?php esc_html_e('Enrollment Number', 'school-management'); ?>:</span>
                                    <span class="wlsm-st-details-list-value"><?php echo esc_html($student->enrollment_number); ?></span>
                                </li>
                            <?php endif; ?>
                            <?php if ($school_admission_number) : ?>
                                <li>
                                    <span class="wlsm-st-details-list-key"><?php esc_html_e('Admission Number', 'school-management'); ?>:</span>
                                    <span class="wlsm-st-details-list-value"><?php echo esc_html($student->admission_number); ?></span>
                                </li>
                            <?php endif; ?>
                            <li>
                                <span class="wlsm-st-details-list-key"><?php esc_html_e('Session', 'school-management'); ?>:</span>
                                <span class="wlsm-st-details-list-value"><?php echo esc_html(WLSM_M_Session::get_label_text($student->session_label)); ?></span>
                            </li>
                            <li>
                                <span class="wlsm-st-details-list-key"><?php esc_html_e('Class', 'school-management'); ?>:</span>
                                <span class="wlsm-st-details-list-value"><?php echo esc_html(WLSM_M_Class::get_label_text($student->class_label)); ?></span>
                            </li>
                            <li>
                                <span class="wlsm-st-details-list-key"><?php esc_html_e('Section', 'school-management'); ?>:</span>
                                <span class="wlsm-st-details-list-value"><?php echo esc_html(WLSM_M_Class::get_label_text($student->section_label)); ?></span>
                            </li>
                            <li>
                                <span class="wlsm-st-details-list-key"><?php esc_html_e('Roll Number', 'school-management'); ?>:</span>
                                <span class="wlsm-st-details-list-value"><?php echo esc_html(WLSM_M_Staff_Class::get_roll_no_text($student->roll_number)); ?></span>
                            </li>
                            <li>
                                <span class="wlsm-st-details-list-key"><?php esc_html_e('Father\'s Name', 'school-management'); ?>:</span>
                                <span class="wlsm-st-details-list-value"><?php echo esc_html(WLSM_M_Staff_Class::get_name_text($student->father_name)); ?></span>
                            </li>
                            <li>
                                <span class="wlsm-st-details-list-key"><?php esc_html_e('Father\'s Phone', 'school-management'); ?>:</span>
                                <span class="wlsm-st-details-list-value"><?php echo esc_html(WLSM_M_Staff_Class::get_name_text($student->father_phone)); ?></span>
                            </li>
                            <li>
                                <span class="wlsm-st-details-list-key"><?php esc_html_e('ID Card', 'school-management'); ?>:</span>
                                <span class="wlsm-st-details-list-value">
                                    <a class="wlsm-st-print-id-card" data-id-card="<?php echo esc_attr($user_id); ?>" data-user-id="<?php echo esc_attr($user_id); ?>"
                                        data-nonce="<?php echo esc_attr(wp_create_nonce('st-print-id-card-' . $user_id)); ?>"
                                        href="#"
                                        data-message-title="<?php echo esc_attr__('Print ID Card', 'school-management'); ?>"
                                        title="<?php esc_attr_e('Print ID Card', 'school-management'); ?>">
                                        <i class="fa fa-print"></i>
                                    </a>
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Stats Section -->
            <div class="wlsm-stats-section">
                <!-- Attendance Card -->
                <div class="wlsm-card wlsm-stats-card has-chart">
                    <div class="wlsm-stats-headerr">

                        <h4><?php esc_html_e('Attendance', 'school-management'); ?></h4>
                    </div>
                    <div class="wlsm-stats-content">
                        <div class="wlsm-stats-value">
                            <?php if (!empty($attendance['percentage_text']) && $attendance['percentage_text'] != 0) : ?>
                                <?php echo esc_html($attendance['percentage_text']); ?>
                            <?php else : ?>
                                <?php esc_html_e('N/A', 'school-management'); ?>
                            <?php endif; ?>
                        </div>

                        <!-- Add canvas for pie chart -->
                        <div class="wlsm-attendance-chart-container">
                            <canvas id="attendanceChart"></canvas>
                        </div>

                        <?php if (
                            !empty($attendance['total_present']) ||
                            !empty($attendance['total_absent']) ||
                            !empty($attendance['total_late']) ||
                            !empty($attendance['total_holiday'])
                        ) : ?>
                            <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    const ctx = document.getElementById('attendanceChart').getContext('2d');
                                    new Chart(ctx, {
                                        type: 'pie',
                                        data: {
                                            labels: [
                                                '<?php esc_html_e('Present', 'school-management'); ?>',
                                                '<?php esc_html_e('Absent', 'school-management'); ?>',
                                                '<?php esc_html_e('Late', 'school-management'); ?>',
                                                '<?php esc_html_e('Holiday', 'school-management'); ?>'
                                            ],
                                            datasets: [{
                                                data: [
                                                    <?php echo esc_js($attendance['total_present']); ?>,
                                                    <?php echo esc_js($attendance['total_absent']); ?>,
                                                    <?php echo esc_js($attendance['total_late']); ?>,
                                                    <?php echo esc_js($attendance['total_holiday']); ?>
                                                ],
                                                backgroundColor: [
                                                    '#4caf50', // Present - Teal
                                                    '#ef5350', // Absent - Coral
                                                    '#03a9f4', // Late - Golden
                                                    '#ff9800' // Holiday - Sky Blue
                                                ],
                                                borderColor: '#ffffff',
                                                borderWidth: 1
                                            }]
                                        },
                                        options: {
                                            responsive: true,
                                            maintainAspectRatio: false,
                                            plugins: {
                                                legend: {
                                                    position: 'bottom',
                                                    labels: {
                                                        padding: 20,
                                                        usePointStyle: true,
                                                        font: {
                                                            size: 12
                                                        }
                                                    }
                                                },
                                                tooltip: {
                                                    callbacks: {
                                                        label: function(context) {
                                                            const label = context.label || '';
                                                            const value = context.raw || 0;
                                                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                                            const percentage = ((value / total) * 100).toFixed(1);
                                                            return `${label}: ${value} (${percentage}%)`;
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    });
                                });
                            </script>
                        <?php endif; ?>

                        <!-- Add attendance statistics below chart -->
                        <div class="wlsm-attendance-stats">
                            <div class="wlsm-attendance-stat present">
                                <i class="fas fa-check-circle"></i>
                                <span><?php esc_html_e('Present:', 'school-management'); ?> <?php echo esc_html($attendance['total_present']); ?></span>
                            </div>
                            <div class="wlsm-attendance-stat absent">
                                <i class="fas fa-times-circle"></i>
                                <span><?php esc_html_e('Absent:', 'school-management'); ?> <?php echo esc_html($attendance['total_absent']); ?></span>
                            </div>
                            <div class="wlsm-attendance-stat late">
                                <i class="fas fa-clock"></i>
                                <span><?php esc_html_e('Late:', 'school-management'); ?> <?php echo esc_html($attendance['total_late']); ?></span>
                            </div>
                            <div class="wlsm-attendance-stat holiday">
                                <i class="fas fa-umbrella-beach"></i>
                                <span><?php esc_html_e('Holiday:', 'school-management'); ?> <?php echo esc_html($attendance['total_holiday']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                             <!-- Fee Status Card -->
                <?php
                // Get payment data
                $payments_query = WLSM_M::payments_query();
                $payments = $wpdb->get_results($wpdb->prepare($payments_query . ' ORDER BY p.ID DESC', $student->ID));

                // Calculate total paid amount
                $total_paid = 0;
                if (!empty($payments)) {
                    foreach ($payments as $payment) {
                        $total_paid += $payment->amount;
                    }
                }

                // Calculate totals
                $student_id = $student->ID;
                $student = WLSM_M_Staff_General::fetch_student($school_id, $session_id, $student_id);
                $fees = WLSM_M_Staff_Accountant::fetch_student_fees($school_id, $student_id);

                // Calculate months in session
                $start_date = new DateTime($student->start_date);
                $end_date = new DateTime($student->end_date);
                $interval = $start_date->diff($end_date);

                // Calculate total months including years (years * 12 + months)
                $months_in_session = ($interval->y * 12) + $interval->m;

                // If you want to consider partial months (days)
                if ($interval->d > 0) {
                    ++$months_in_session; // Add one more month if there are remaining days
                }

                // Initialize fee totals
                $session_onetime_total = 0;
                $session_quarterly_total = 0;
                $session_quadrimester_total = 0;
                $session_half_yearly_total = 0;
                $session_monthly_total = 0;
                $session_yearly_total = 0;

                // Calculate fee totals by period
                foreach ($fees as $fee) {
                    switch ($fee->period) {
                        case 'monthly':
                            $session_monthly_total += intval($fee->amount) * $months_in_session;
                            break;
                        case 'one-time':
                            $session_onetime_total += intval($fee->amount);
                            break;
                        case 'quarterly':
                            $occurrences = ceil($months_in_session / 3);
                            $session_quarterly_total += intval($fee->amount) * $occurrences;
                            break;
                        case 'quadrimester':
                            $occurrences = ceil($months_in_session / 4);
                            $session_quadrimester_total += intval($fee->amount) * $occurrences;
                            break;
                        case 'half-yearly':
                            $occurrences = ceil($months_in_session / 6);
                            $session_half_yearly_total += intval($fee->amount) * $occurrences;
                            break;
                        case 'annually':
                            $occurrences = ceil($months_in_session / 12);
                            $session_yearly_total += intval($fee->amount) * $occurrences;
                            break;
                    }
                }

                // Calculate totals
                $total_payable = $session_monthly_total + $session_onetime_total +
                    $session_quarterly_total + $session_quadrimester_total +
                    $session_half_yearly_total + $session_yearly_total;

                $payment_percentage = $total_payable > 0 ? round(($total_paid / $total_payable) * 100) : 0;
                $remaining_amount = $total_payable - $total_paid;
                ?>

                <div class="wlsm-card wlsm-stats-card">
                    <div class="wlsm-stats-headerr">
                        <h4><?php esc_html_e('Session Fee Breakdown', 'school-management'); ?></h4>
                    </div>
                    <div class="wlsm-stats-content">
                        <table class="wlsm-table" style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr>
                                    <th style="text-align: left; padding: 8px; border-bottom: 1px solid #ddd;"><?php esc_html_e('Period', 'school-management'); ?></th>
                                    <th style="text-align: left; padding: 8px; border-bottom: 1px solid #ddd;"><?php esc_html_e('Session Total', 'school-management'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($session_monthly_total > 0) : ?>
                                    <tr>
                                        <td style="padding: 8px;"><?php esc_html_e('Monthly', 'school-management'); ?></td>
                                        <td style="padding: 8px;"><?php echo esc_html(WLSM_Config::get_money_text($session_monthly_total, $school_id)); ?></td>
                                    </tr>
                                <?php endif; ?>

                                <?php if ($session_quarterly_total > 0) : ?>
                                    <tr>
                                        <td style="padding: 8px;"><?php esc_html_e('Quarterly', 'school-management'); ?></td>
                                        <td style="padding: 8px;"><?php echo esc_html(WLSM_Config::get_money_text($session_quarterly_total, $school_id)); ?></td>
                                    </tr>
                                <?php endif; ?>

                                <?php if ($session_quadrimester_total > 0) : ?>
                                    <tr>
                                        <td style="padding: 8px;"><?php esc_html_e('Quadrimester', 'school-management'); ?></td>
                                        <td style="padding: 8px;"><?php echo esc_html(WLSM_Config::get_money_text($session_quadrimester_total, $school_id)); ?></td>
                                    </tr>
                                <?php endif; ?>

                                <?php if ($session_half_yearly_total > 0) : ?>
                                    <tr>
                                        <td style="padding: 8px;"><?php esc_html_e('Half Yearly', 'school-management'); ?></td>
                                        <td style="padding: 8px;"><?php echo esc_html(WLSM_Config::get_money_text($session_half_yearly_total, $school_id)); ?></td>
                                    </tr>
                                <?php endif; ?>

                                <?php if ($session_yearly_total > 0) : ?>
                                    <tr>
                                        <td style="padding: 8px;"><?php esc_html_e('Annual', 'school-management'); ?></td>
                                        <td style="padding: 8px;"><?php echo esc_html(WLSM_Config::get_money_text($session_yearly_total, $school_id)); ?></td>
                                    </tr>
                                <?php endif; ?>

                                <?php if ($session_onetime_total > 0) : ?>
                                    <tr>
                                        <td style="padding: 8px;"><?php esc_html_e('One Time', 'school-management'); ?></td>
                                        <td style="padding: 8px;"><?php echo esc_html(WLSM_Config::get_money_text($session_onetime_total, $school_id)); ?></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php if ($vehicle_id && !empty($transportation_details)) :
                    $transport = $transportation_details[0]; ?>
                    <!-- Transport Stats Card -->
                    <div class="wlsm-card wlsm-stats-card wlsm-transport-stats">
                        <div class="wlsm-stats-header">
                            <div class="wlsm-stats-icon">
                                <i class="fas fa-bus"></i>
                            </div>
                            <h4><?php esc_html_e('Transportation', 'school-management'); ?></h4>
                        </div>
                        <div class="wlsm-stats-content">
                            <div class="wlsm-stats-value">
                                <?php echo esc_html($transport->name); ?>
                            </div>
                            <div class="wlsm-transport-details">
                                <div class="wlsm-transport-info">
                                    <i class="fas fa-bus"></i>
                                    <span><?php echo esc_html($transport->vehicle_number); ?></span>
                                </div>
                                <div class="wlsm-transport-info">
                                    <i class="fas fa-money-bill"></i>
                                    <span><?php echo esc_html(WLSM_Config::get_money_text($transport->fare, $school_id)); ?></span>
                                </div>
                                <?php if ($transport->driver_name) : ?>
                                    <div class="wlsm-transport-info">
                                        <i class="fas fa-user"></i>
                                        <span><?php echo esc_html($transport->driver_name); ?>
                                            <?php if ($transport->driver_phone) : ?>
                                                <small>(<?php echo esc_html($transport->driver_phone); ?>)</small>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Notices Section -->
        <div class="wlsm-section-heading">
            <h2><i class="fas fa-bullhorn"></i> <?php esc_html_e('Latest Notices', 'school-management'); ?></h2>
        </div>
        <div class="">
            <div class="wlsm-st-recent-notices-section">
                <?php if ($filtered_notices) : ?>
                    <ul class="wlsm-st-notices">
                        <?php foreach ($filtered_notices as $notice) :
                            $link_to = $notice->link_to;
                            $link = '#';
                            if ('url' === $link_to && !empty($notice->url)) {
                                $link = $notice->url;
                            } elseif ('attachment' === $link_to && !empty($notice->attachment)) {
                                $link = wp_get_attachment_url($notice->attachment);
                            }
                            $notice_date = DateTime::createFromFormat('Y-m-d H:i:s', $notice->created_at);
                            $notice_date->setTime(0, 0, 0);
                            $interval = (new DateTime())->diff($notice_date);
                        ?>
                            <li>
                                <a target="_blank" href="<?php echo esc_url($link); ?>">
                                    <?php echo esc_html(stripslashes($notice->title)); ?>
                                    <?php if ($interval->days < 7) : ?>
                                        <img class="wlsm-st-notice-new" src="<?php echo esc_url(WLSM_PLUGIN_URL . 'assets/images/newicon.gif'); ?>">
                                    <?php endif; ?>
                                    <span class="wlsm-st-notice-date">
                                        <?php echo esc_html(WLSM_Config::get_date_text($notice->created_at)); ?>
                                    </span>
                                </a>
                                <p><?php echo esc_html(stripslashes($notice->description)); ?></p>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <div class="wlsm-no-notices">
                        <i class="fas fa-bell-slash"></i>
                        <p><?php esc_html_e('No notices available', 'school-management'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php } ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>