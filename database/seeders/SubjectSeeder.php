<?php

namespace Database\Seeders;

use App\Models\Subject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Define the subjects for Informatics program
        $subjects = [
            ['name' => 'Introduction to Programming', 'description' => 'An introductory course to programming using popular programming languages like Python and Java.'],
            ['name' => 'Data Structures and Algorithms', 'description' => 'Study of data structures such as arrays, linked lists, trees, and graphs, along with the algorithms to manipulate them.'],
            ['name' => 'Database Systems', 'description' => 'Learn about relational databases, SQL, and techniques for database management and optimization.'],
            ['name' => 'Computer Networks', 'description' => 'An introduction to computer networking principles, protocols, and internet technologies.'],
            ['name' => 'Operating Systems', 'description' => 'An overview of the basic functions of operating systems such as process management, memory management, and file systems.'],
            ['name' => 'Software Engineering', 'description' => 'An in-depth study of software development methodologies, project management, and software testing.'],
            ['name' => 'Web Development', 'description' => 'Learn how to design and build modern web applications using HTML, CSS, JavaScript, and backend frameworks.'],
            ['name' => 'Artificial Intelligence', 'description' => 'Introduction to the basics of AI, including machine learning, neural networks, and data analysis techniques.'],
            ['name' => 'Mobile App Development', 'description' => 'A course covering mobile application development for platforms like Android and iOS.'],
            ['name' => 'Cybersecurity', 'description' => 'Learn about securing networks, systems, and data from cyber-attacks and understanding cryptography and security protocols.'],
            ['name' => 'Human-Computer Interaction', 'description' => 'The study of how people interact with computers and designing user-friendly interfaces.'],
            ['name' => 'Cloud Computing', 'description' => 'Introduction to cloud platforms like AWS, Azure, and Google Cloud, and how to use them for scalable computing resources.'],
            ['name' => 'Big Data', 'description' => 'Learn the techniques for analyzing and processing large datasets using tools like Hadoop and Spark.'],
            ['name' => 'Machine Learning', 'description' => 'Study of algorithms that allow machines to learn from data and improve over time, including supervised and unsupervised learning.'],
            ['name' => 'Digital Logic Design', 'description' => 'Study of basic digital components like gates, flip-flops, and how they are used to design digital systems.'],
            ['name' => 'Computer Graphics', 'description' => 'Introduction to computer graphics techniques including rendering, 3D modeling, and animation.'],
            ['name' => 'Cloud Storage and Virtualization', 'description' => 'Learn about cloud storage systems and virtualization technologies used in modern data centers.'],
            ['name' => 'Computer Vision', 'description' => 'Study of how computers can be made to interpret and process visual information from the world, using algorithms and machine learning.'],
            ['name' => 'Game Development', 'description' => 'Learn how to create video games, covering game engines, graphics, AI, and physics simulation.'],
            ['name' => 'Data Analytics', 'description' => 'Introduction to data analysis techniques including data wrangling, statistics, and visualization using tools like Excel and Python.'],
            ['name' => 'Distributed Systems', 'description' => 'Study of systems where components are located on different networked computers that communicate and coordinate their actions.'],
            ['name' => 'Business Intelligence', 'description' => 'Using technology to analyze data and provide actionable insights to assist in business decision-making.'],
            ['name' => 'Parallel Computing', 'description' => 'Techniques for parallel processing to improve the performance of computational tasks on multi-core processors or distributed systems.'],
            ['name' => 'Natural Language Processing', 'description' => 'Study of how computers can understand and process human language, including text and speech.'],
            ['name' => 'Cryptography', 'description' => 'Introduction to techniques used for securing communication and data storage, including encryption algorithms and protocols.'],
            ['name' => 'Ethical Hacking', 'description' => 'Learn how to identify and fix security vulnerabilities in systems by simulating attacks in an ethical manner.'],
            ['name' => 'Internet of Things (IoT)', 'description' => 'Learn about the network of connected devices, sensors, and actuators that exchange data over the internet.'],
            ['name' => 'Blockchain Technology', 'description' => 'Study the principles of blockchain and its application in cryptocurrencies, secure transactions, and smart contracts.'],
            ['name' => 'Advanced Database Systems', 'description' => 'A deep dive into complex database systems, including NoSQL databases, graph databases, and data warehousing.'],
            ['name' => 'Virtual Reality', 'description' => 'Study the technologies used to create immersive virtual environments and how they are applied in gaming and simulation.'],
            ['name' => 'Data Science', 'description' => 'Learn how to analyze and interpret complex data using statistical methods, programming, and machine learning.'],
            ['name' => 'IT Project Management', 'description' => 'A focus on managing technology projects, including planning, execution, and team collaboration.'],
            ['name' => 'Advanced Machine Learning', 'description' => 'A more in-depth exploration of machine learning algorithms, including reinforcement learning and deep learning.'],
            ['name' => 'Software Testing', 'description' => 'Study different software testing methodologies and how to ensure the quality of software applications.'],
            ['name' => 'Digital Forensics', 'description' => 'Learn how to investigate and recover data from digital devices to solve cybercrimes or data breaches.'],
            ['name' => 'Data Mining', 'description' => 'Techniques for discovering patterns in large datasets, using statistical models and machine learning algorithms.'],
            ['name' => 'AI Ethics', 'description' => 'Examine the ethical considerations and societal impacts of artificial intelligence and machine learning.'],
            ['name' => 'Web Security', 'description' => 'Study of the security aspects of web applications and how to prevent vulnerabilities such as SQL injection and cross-site scripting.'],
            ['name' => 'Smart Cities', 'description' => 'Learn how IoT, big data, and AI are used to build efficient, sustainable, and intelligent urban environments.'],
            ['name' => 'Business Process Automation', 'description' => 'Study how software and technology are used to automate business processes and improve efficiency.'],
            ['name' => 'Advanced Networking', 'description' => 'A deeper dive into networking technologies, protocols, and advanced network configurations.'],
            ['name' => 'Computational Biology', 'description' => 'Using computational techniques to understand biological data, including genomics and bioinformatics.'],
            ['name' => 'Digital Transformation', 'description' => 'How businesses and organizations are using technology to fundamentally change their operations and models.'],
            ['name' => 'Cloud Application Development', 'description' => 'Learn how to design and build applications that are hosted on cloud platforms like AWS or Google Cloud.'],
            ['name' => 'Robotics', 'description' => 'Introduction to robotics, including designing, building, and programming autonomous robots.'],
            ['name' => 'Quantum Computing', 'description' => 'Explore the principles and applications of quantum computing, a next-generation technology with enormous computational power.'],
            ['name' => 'Data Warehousing', 'description' => 'Learn how to store and manage large volumes of data in a centralized repository for reporting and analysis.'],
            ['name' => 'Advanced Artificial Intelligence', 'description' => 'Study the advanced methods of AI, including deep learning, natural language processing, and computer vision.'],
            ['name' => 'Embedded Systems', 'description' => 'Learn how to design and program embedded systems used in devices like medical equipment, cars, and consumer electronics.'],
            ['name' => 'Smart Devices and Sensors', 'description' => 'Learn about the design and development of smart devices and sensors that collect and transmit data.'],
            ['name' => 'Cloud Infrastructure', 'description' => 'Study the architecture and components of cloud infrastructure, including servers, storage, and networking.'],
            ['name' => 'Artificial Neural Networks', 'description' => 'Introduction to neural networks, the building blocks of deep learning, and how they are used in various AI applications.'],
            ['name' => 'Advanced Data Analytics', 'description' => 'In-depth analysis techniques, including predictive modeling, time series analysis, and machine learning algorithms.'],
            ['name' => 'Tech Entrepreneurship', 'description' => 'Learn about the business side of technology, including startup creation, funding, and scaling.'],
        ];

        // Insert subjects into the database
        foreach ($subjects as $subject) {
            Subject::create($subject);
        }
    }
}