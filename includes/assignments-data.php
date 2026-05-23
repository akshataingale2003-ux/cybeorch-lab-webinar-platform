<?php
declare(strict_types=1);

/** Open assignment listings for the public Assignments page */
function getAssignments(): array
{
    return [
        [
            'slug'        => 'full-stack-web-developer',
            'title'       => 'Full Stack Web Developer',
            'category'    => 'Software Development',
            'type'        => 'Internship',
            'description' => 'Build and maintain PHP/React modules for client dashboards, APIs, and admin panels in a live product environment.',
            'skills'      => 'PHP, MySQL, JavaScript, REST APIs',
            'duration'    => '8–12 weeks',
            'mode'        => 'Hybrid',
            'status'      => 'Open',
            'apply'       => 'index.php?register_required=1',
        ],
        [
            'slug'        => 'cybersecurity-analyst',
            'title'       => 'Cybersecurity Analyst',
            'category'    => 'Cybersecurity',
            'type'        => 'Project',
            'description' => 'Support vulnerability assessments, secure deployment reviews, and documentation for SME web applications.',
            'skills'      => 'OWASP, Nmap, Burp Suite, Linux',
            'duration'    => '6 weeks',
            'mode'        => 'Remote',
            'status'      => 'Open',
            'apply'       => 'index.php?register_required=1',
        ],
        [
            'slug'        => 'ui-ux-designer',
            'title'       => 'UI/UX Designer',
            'category'    => 'Design',
            'type'        => 'Freelance',
            'description' => 'Design responsive interfaces and prototypes for EdTech and startup MVP products under mentor review.',
            'skills'      => 'Figma, Wireframing, Design systems',
            'duration'    => '4–8 weeks',
            'mode'        => 'Remote',
            'status'      => 'Open',
            'apply'       => 'register-freelancer.php',
        ],
        [
            'slug'        => 'ai-automation-developer',
            'title'       => 'AI Automation Developer',
            'category'    => 'AI & Automation',
            'type'        => 'Project',
            'description' => 'Integrate AI tools into internal workflows: chatbots, document processing, and automation scripts for operations teams.',
            'skills'      => 'Python, APIs, Prompt engineering',
            'duration'    => '6 weeks',
            'mode'        => 'Remote',
            'status'      => 'Open',
            'apply'       => 'index.php?register_required=1',
        ],
        [
            'slug'        => 'devops-cloud-intern',
            'title'       => 'DevOps & Cloud Intern',
            'category'    => 'DevOps',
            'type'        => 'Internship',
            'description' => 'Assist with CI/CD pipelines, server hardening, Docker deployments, and monitoring for lab and client projects.',
            'skills'      => 'Linux, Docker, AWS basics, Git',
            'duration'    => '10 weeks',
            'mode'        => 'On-site / Hybrid',
            'status'      => 'Open',
            'apply'       => 'index.php?register_required=1',
        ],
        [
            'slug'        => 'mobile-app-developer',
            'title'       => 'Mobile App Developer',
            'category'    => 'Mobile',
            'type'        => 'Freelance',
            'description' => 'Contribute to Flutter/React Native features for product MVPs with weekly sprint reviews and code mentorship.',
            'skills'      => 'Flutter or React Native, Firebase',
            'duration'    => 'Project-based',
            'mode'        => 'Remote',
            'status'      => 'Open',
            'apply'       => 'register-freelancer.php',
        ],
        [
            'slug'        => 'qa-security-testing',
            'title'       => 'QA & Security Testing',
            'category'    => 'Quality Assurance',
            'type'        => 'Project',
            'description' => 'Execute test plans, regression suites, and basic security checks before client releases and demo milestones.',
            'skills'      => 'Manual testing, Selenium, OWASP basics',
            'duration'    => '4 weeks',
            'mode'        => 'Remote',
            'status'      => 'Closing Soon',
            'apply'       => 'index.php?register_required=1',
        ],
        [
            'slug'        => 'blockchain-utility-developer',
            'title'       => 'Blockchain Utility Developer',
            'category'    => 'Web3',
            'type'        => 'Freelance',
            'description' => 'Build smart contract utilities and Web3 integration prototypes for internal R&D and startup consulting engagements.',
            'skills'      => 'Solidity, Web3.js, Ethereum',
            'duration'    => 'Project-based',
            'mode'        => 'Remote',
            'status'      => 'Open',
            'apply'       => 'register-freelancer.php',
        ],
        [
            'slug'        => 'soc-analyst-intern',
            'title'       => 'SOC Analyst Intern',
            'category'    => 'Cybersecurity',
            'type'        => 'Internship',
            'description' => 'Monitor security alerts, triage incidents, and support SIEM log analysis and incident response playbooks in live lab environments.',
            'skills'      => 'SIEM, Log analysis, Networking, Incident response',
            'duration'    => '8 weeks',
            'mode'        => 'Hybrid',
            'status'      => 'Open',
            'apply'       => 'index.php?register_required=1',
        ],
    ];
}

function getAssignmentBySlug(string $slug): ?array
{
    $slug = trim($slug);
    if ($slug === '') {
        return null;
    }
    foreach (getAssignments() as $assignment) {
        if (($assignment['slug'] ?? '') === $slug) {
            return $assignment;
        }
    }
    return null;
}

function assignmentRegisterUrl(array $assignment): string
{
    return url('assignment-register.php?assignment=' . urlencode((string) ($assignment['slug'] ?? '')));
}

function assignmentStatusBadgeClass(string $status): string
{
    return match (strtolower($status)) {
        'open' => 'badge-free',
        'closing soon' => 'badge-live',
        'filled', 'closed' => 'badge-paid',
        default => 'badge-paid',
    };
}

function assignmentTypeBadgeClass(string $type): string
{
    return match (strtolower($type)) {
        'freelance' => 'badge-paid',
        'internship' => 'badge-free',
        default => 'badge-paid',
    };
}
