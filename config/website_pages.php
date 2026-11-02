<?php

return [
    'pages' => [
        'home' => [
            'title' => 'Homepage',
            'meta_title' => 'Financial support for your next step',
            'meta_description' => "Explore lending options and apply online with Don't Stuck Solutions in Nairobi.",
            'fields' => [
                'hero_heading' => ['label' => 'Hero heading', 'type' => 'text', 'default' => "Make room for what's next."],
                'hero_copy' => ['label' => 'Hero copy', 'type' => 'textarea', 'default' => 'A clear, respectful lending process for people with plans to move forward. Tell us what you need and our team will guide you through the next step.'],
                'intro_heading' => ['label' => 'Loan section heading', 'type' => 'text', 'default' => 'Financial support for real-life plans.'],
                'process_heading' => ['label' => 'Application process heading', 'type' => 'text', 'default' => 'A process that keeps you in the loop.'],
                'process_copy' => ['label' => 'Application process introduction', 'type' => 'textarea', 'default' => 'We keep each step clear, from your first application to a decision from our team.'],
                'values_heading' => ['label' => 'Why choose us heading', 'type' => 'text', 'default' => 'Good support starts with listening.'],
                'values_copy' => ['label' => 'Why choose us copy', 'type' => 'textarea', 'default' => 'Financial decisions deserve time, clear information and a real person who understands your questions. That’s the kind of service we work to provide.'],
                'cta_heading' => ['label' => 'Call to action heading', 'type' => 'text', 'default' => 'Ready to talk through your next step?'],
            ],
        ],
        'about' => [
            'title' => 'About us',
            'meta_title' => 'About Don’t Stuck Solutions',
            'meta_description' => "Learn about Don't Stuck Solutions and our commitment to clear, respectful lending support.",
            'fields' => [
                'heading' => ['label' => 'Page heading', 'type' => 'text', 'default' => 'Enriching lives, one step at a time.'],
                'lead' => ['label' => 'Introduction', 'type' => 'textarea', 'default' => 'We’re a Nairobi-based financial services company focused on thoughtful support and clear conversations.'],
                'story_heading' => ['label' => 'Our approach heading', 'type' => 'text', 'default' => 'Practical support begins with understanding.'],
                'story_copy' => ['label' => 'Our approach', 'type' => 'textarea', 'default' => 'Every person comes to us with a different goal. Our role is to listen, review each request carefully and make the next steps easy to understand. We believe people should have enough information to make a considered choice.'],
                'mission' => ['label' => 'Mission', 'type' => 'textarea', 'default' => 'Help people make progress through responsible, accessible financial support.'],
                'vision' => ['label' => 'Vision', 'type' => 'textarea', 'default' => 'Be a trusted partner for individuals planning their next chapter.'],
                'values_heading' => ['label' => 'Values heading', 'type' => 'text', 'default' => 'Built on trust, earned every day.'],
            ],
        ],
        'contact' => [
            'title' => 'Contact us',
            'meta_title' => 'Contact Don’t Stuck Solutions',
            'meta_description' => "Get in touch with Don't Stuck Solutions in Nairobi for help with a loan or application.",
            'fields' => [
                'heading' => ['label' => 'Page heading', 'type' => 'text', 'default' => 'Let’s start with a conversation.'],
                'lead' => ['label' => 'Introduction', 'type' => 'textarea', 'default' => 'Questions about an application or a loan option? Send us a note or reach our Nairobi team directly.'],
                'phone' => ['label' => 'Public phone number', 'type' => 'text', 'default' => '+254 725 228 380'],
                'email' => ['label' => 'Public email address', 'type' => 'text', 'default' => 'dontstucksolutions2@gmail.com'],
                'postal_address' => ['label' => 'Postal address', 'type' => 'textarea', 'default' => 'P.O. Box 3797-00506, Nairobi, Kenya'],
                'business_hours' => ['label' => 'Business hours', 'type' => 'textarea', 'default' => 'Contact our team for current business hours.'],
            ],
        ],
    ],
];
