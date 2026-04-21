<?php
session_start();
$base_url = "http://localhost/smartdrive_x/";
$page_title = "Careers | SmartDrive X";
include '../includes/header.php';
?>

<style>
    .careers-hero {
        background: linear-gradient(135deg, #1a1e16 0%, #2b3327 50%, #4a5c43 100%);
        padding: 120px 0 100px; color: white; position: relative; overflow: hidden;
    }
    .careers-hero::before {
        content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="80" r="1.5" fill="rgba(136,156,124,0.06)"/><circle cx="80" cy="20" r="1" fill="rgba(136,156,124,0.04)"/></svg>') repeat;
        background-size: 50px 50px;
    }
    .section-label { color: #889c7c; font-weight: 800; text-transform: uppercase; letter-spacing: 3px; font-size: 0.8rem; }
    .perk-card {
        background: white; border-radius: 20px; padding: 35px 25px; text-align: center; height: 100%;
        border: 1px solid rgba(0,0,0,0.05); box-shadow: 0 10px 30px rgba(0,0,0,0.04);
        transition: all 0.3s ease;
    }
    .perk-card:hover { transform: translateY(-8px); box-shadow: 0 20px 40px rgba(74,92,67,0.12); }
    .perk-icon { width: 70px; height: 70px; border-radius: 18px; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; font-size: 1.5rem; }
    .job-card {
        background: white; border-radius: 20px; padding: 30px; border: 1px solid rgba(0,0,0,0.06);
        box-shadow: 0 8px 25px rgba(0,0,0,0.03); transition: all 0.3s ease; position: relative; overflow: hidden;
    }
    .job-card:hover { transform: translateY(-5px); box-shadow: 0 15px 40px rgba(74,92,67,0.1); border-color: rgba(74,92,67,0.2); }
    .job-card::before {
        content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%;
        background: linear-gradient(to bottom, #4a5c43, #889c7c); opacity: 0; transition: opacity 0.3s;
    }
    .job-card:hover::before { opacity: 1; }
    .tag { display: inline-flex; align-items: center; padding: 6px 14px; border-radius: 50px; font-weight: 700; font-size: 0.75rem; }
    .btn-sage { background: #2b3327; color: white; }
    .btn-sage:hover { background: #1e241c; color: white; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(43,51,39,0.2); }
</style>

<section class="careers-hero">
    <div class="container position-relative z-2 text-center">
        <span class="section-label d-block mb-3" data-aos="fade-up"><i class="fas fa-rocket me-2"></i>Join Our Team</span>
        <h1 class="fw-black display-3 mb-4" data-aos="fade-up" data-aos-delay="100">Build the Future of<br><span style="color: #889c7c;">Urban Mobility</span></h1>
        <p class="lead opacity-75 fw-bold mb-5 mx-auto" style="max-width: 550px;" data-aos="fade-up" data-aos-delay="200">Work on challenging problems, grow with a passionate team, and help millions drive smarter.</p>
        <a href="#openings" class="btn btn-light rounded-pill fw-bold px-5 py-3 shadow-sm" data-aos="fade-up" data-aos-delay="300"><i class="fas fa-briefcase me-2"></i>View Open Positions</a>
    </div>
</section>

<!-- PERKS -->
<section class="py-5 my-5">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <span class="section-label d-block mb-3">Why SmartDrive X?</span>
            <h2 class="fw-black display-5 text-dark">Perks & Benefits</h2>
        </div>
        <div class="row g-4">
            <?php
            $perks = [
                ['icon' => 'fa-laptop-code', 'title' => 'Modern Tech Stack', 'desc' => 'Work with PHP 8+, MySQL, Bootstrap 5, and modern JavaScript.', 'bg' => 'rgba(74,92,67,0.1)', 'color' => '#4a5c43'],
                ['icon' => 'fa-graduation-cap', 'title' => 'Learning Budget', 'desc' => 'Annual learning allowance for courses, certifications, and conferences.', 'bg' => 'rgba(255,193,7,0.1)', 'color' => '#ffc107'],
                ['icon' => 'fa-clock', 'title' => 'Flexible Hours', 'desc' => 'Core hours with flexibility to manage your own schedule.', 'bg' => 'rgba(13,202,240,0.1)', 'color' => '#0dcaf0'],
                ['icon' => 'fa-car-alt', 'title' => 'Free Rentals', 'desc' => 'Employees get complimentary weekend rentals from our fleet.', 'bg' => 'rgba(25,135,84,0.1)', 'color' => '#198754'],
                ['icon' => 'fa-users', 'title' => 'Small Team, Big Impact', 'desc' => 'Your work directly impacts thousands of users. No bureaucracy.', 'bg' => 'rgba(111,66,193,0.1)', 'color' => '#6f42c1'],
                ['icon' => 'fa-chart-line', 'title' => 'Growth Path', 'desc' => 'Clear progression from intern to tech lead with mentorship support.', 'bg' => 'rgba(253,126,20,0.1)', 'color' => '#fd7e14'],
            ];
            foreach ($perks as $i => $p):
            ?>
            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="<?php echo ($i % 3) * 100; ?>">
                <div class="perk-card">
                    <div class="perk-icon" style="background: <?php echo $p['bg']; ?>; color: <?php echo $p['color']; ?>;"><i class="fas <?php echo $p['icon']; ?>"></i></div>
                    <h6 class="fw-black text-dark mb-2"><?php echo $p['title']; ?></h6>
                    <p class="text-muted fw-bold small mb-0"><?php echo $p['desc']; ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- OPEN POSITIONS -->
<section class="py-5" id="openings" style="background: #f8f9f7;">
    <div class="container py-4">
        <div class="text-center mb-5" data-aos="fade-up">
            <span class="section-label d-block mb-3">Open Positions</span>
            <h2 class="fw-black display-5 text-dark">Current Openings</h2>
        </div>
        <div class="row g-4" style="max-width: 900px; margin: 0 auto;">
            <?php
            $jobs = [
                ['title' => 'Full Stack PHP Developer', 'type' => 'Full-Time', 'loc' => 'Ahmedabad', 'dept' => 'Engineering', 'color' => '#4a5c43', 'skills' => ['PHP 8+', 'MySQL', 'Bootstrap', 'JavaScript']],
                ['title' => 'UI/UX Designer', 'type' => 'Full-Time', 'loc' => 'Remote', 'dept' => 'Design', 'color' => '#6f42c1', 'skills' => ['Figma', 'CSS3', 'Prototyping', 'User Research']],
                ['title' => 'DevOps Intern', 'type' => 'Internship', 'loc' => 'Ahmedabad', 'dept' => 'Infrastructure', 'color' => '#0dcaf0', 'skills' => ['Linux', 'Apache', 'MySQL', 'Git']],
                ['title' => 'Marketing & Growth Lead', 'type' => 'Part-Time', 'loc' => 'Remote', 'dept' => 'Marketing', 'color' => '#fd7e14', 'skills' => ['SEO', 'Social Media', 'Analytics', 'Content']],
            ];
            foreach ($jobs as $i => $j):
            ?>
            <div class="col-12" data-aos="fade-up" data-aos-delay="<?php echo $i * 100; ?>">
                <div class="job-card">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                        <div>
                            <span class="tag mb-2" style="background: rgba(<?php echo $j['color'] == '#4a5c43' ? '74,92,67' : ($j['color'] == '#6f42c1' ? '111,66,193' : ($j['color'] == '#0dcaf0' ? '13,202,240' : '253,126,20')); ?>,0.1); color: <?php echo $j['color']; ?>;"><?php echo $j['dept']; ?></span>
                            <h5 class="fw-black text-dark mb-2"><?php echo $j['title']; ?></h5>
                            <div class="d-flex gap-3 text-muted small fw-bold">
                                <span><i class="fas fa-briefcase me-1"></i><?php echo $j['type']; ?></span>
                                <span><i class="fas fa-map-marker-alt me-1"></i><?php echo $j['loc']; ?></span>
                            </div>
                        </div>
                        <a href="<?php echo $base_url; ?>pages/contact.php" class="btn btn-sage rounded-pill fw-bold px-4 py-2 shadow-sm align-self-center">Apply Now <i class="fas fa-arrow-right ms-1"></i></a>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-3 pt-3 border-top">
                        <?php foreach ($j['skills'] as $s): ?>
                            <span class="badge bg-light text-dark border rounded-pill px-3 py-2 fw-bold" style="font-size: 0.75rem;"><?php echo $s; ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="py-5" style="background: linear-gradient(135deg, #2b3327, #4a5c43); color: white;">
    <div class="container text-center py-5" data-aos="zoom-in">
        <h2 class="fw-black display-5 mb-3">Don't See Your Role?</h2>
        <p class="opacity-75 fw-bold mb-5 mx-auto" style="max-width: 500px;">We're always looking for talented people. Send us your resume and we'll reach out when the right opportunity opens.</p>
        <a href="<?php echo $base_url; ?>pages/contact.php" class="btn btn-light rounded-pill fw-bold px-5 py-3 shadow-lg"><i class="fas fa-envelope me-2"></i>Get In Touch</a>
    </div>
</section>

<?php include '../includes/footer.php'; ?>
