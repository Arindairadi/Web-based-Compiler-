<?php
require_once 'includes/auth.php';
$pageTitle = 'CompilerHub | Advanced Compiler Visualization';
include 'includes/header.php';
?>

<!-- Additional CSS for homepage (from original index) -->
<style>
    :root {
        --primary: #00ff9d;
        --primary-dark: #00d9a6;
        --secondary: #6c63ff;
        --accent: #ff2e63;
        --dark: #0a192f;
        --darker: #071121;
        --light: #ccd6f6;
        --gray: #8892b0;
        --neon-glow: 0 0 20px var(--primary);
    }
    .tech-grid {
        position: fixed;
        width: 100%;
        height: 100%;
        background-image: linear-gradient(rgba(0,255,157,0.03) 1px, transparent 1px), linear-gradient(90deg, rgba(0,255,157,0.03) 1px, transparent 1px);
        background-size: 50px 50px;
        z-index: -3;
        animation: gridMove 20s linear infinite;
        pointer-events: none;
    }
    @keyframes gridMove { 0% { transform: translate(0,0); } 100% { transform: translate(50px,50px); } }
    #canvas3d {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: -2;
        opacity: 0.4;
        pointer-events: none;
    }
    .particles-container {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: -1;
        pointer-events: none;
    }
    .hero {
        padding: 100px 0 80px;
        text-align: center;
        position: relative;
    }
    .hero-title {
        font-family: 'Orbitron', sans-serif;
        font-size: 4rem;
        background: linear-gradient(135deg, var(--primary), var(--secondary), var(--accent));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    .hero-subtitle {
        font-size: 1.2rem;
        color: var(--gray);
        max-width: 800px;
        margin: 20px auto;
    }
    .hero-typed {
        font-size: 1.3rem;
        color: var(--primary);
        font-family: 'JetBrains Mono', monospace;
        margin: 30px 0;
        min-height: 60px;
    }
    .hero-stats {
        display: flex;
        justify-content: center;
        gap: 40px;
        margin-top: 50px;
        flex-wrap: wrap;
    }
    .stat-item {
        background: rgba(17,34,64,0.3);
        border-radius: 15px;
        padding: 20px;
        min-width: 150px;
        border: 1px solid rgba(0,255,157,0.1);
        transition: 0.3s;
    }
    .stat-item:hover { transform: translateY(-5px); border-color: var(--primary); box-shadow: var(--neon-glow); }
    .stat-number {
        font-size: 2.5rem;
        font-weight: 700;
        color: var(--primary);
        font-family: 'Orbitron', sans-serif;
    }
    .section-title {
        text-align: center;
        font-family: 'Orbitron', sans-serif;
        font-size: 2.5rem;
        color: var(--primary);
        margin-bottom: 20px;
    }
    .languages-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 30px;
        margin: 40px 0;
    }
    .language-card {
        background: linear-gradient(145deg, rgba(17,34,64,0.8), rgba(10,25,47,0.8));
        border-radius: 20px;
        border: 1px solid rgba(0,255,157,0.1);
        transition: 0.4s;
        overflow: hidden;
    }
    .language-card:hover { transform: translateY(-10px); box-shadow: var(--neon-glow); border-color: var(--primary); }
    .card-header {
        padding: 25px;
        display: flex;
        align-items: center;
        gap: 15px;
        border-bottom: 1px solid rgba(0,255,157,0.1);
    }
    .language-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        color: white;
    }
    .java { background: linear-gradient(135deg, #007396, #f89820); }
    .c { background: linear-gradient(135deg, #A8B9CC, #555555); }
    .swift { background: linear-gradient(135deg, #FA7343, #F05138); }
    .brainfuck { background: linear-gradient(135deg, #2C3E50, #34495E); }
    .go { background: linear-gradient(135deg, #00ADD8, #00B4A0); }
    .python { background: linear-gradient(135deg, #306998, #FFD43B); }
    .javascript { background: linear-gradient(135deg, #F7DF1E, #323330); }
    .php { background: linear-gradient(135deg, #4F5B93, #8892BF); }
    .r { background: linear-gradient(135deg, #276DC3, #5A8EC9); }
    .playground { background: linear-gradient(135deg, #E34F26, #1572B6); }
    .language-title { font-size: 1.6rem; font-family: 'Orbitron', sans-serif; color: #ccd6f6; }
    .card-body { padding: 25px; }
    .language-description { color: var(--gray); margin-bottom: 20px; }
    .language-link {
        display: block;
        text-align: center;
        padding: 12px;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: #0a192f;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        transition: 0.3s;
    }
    .feature-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 30px;
        margin: 40px 0;
    }
    .feature-item {
        background: rgba(0,0,0,0.3);
        border-radius: 15px;
        padding: 30px;
        text-align: center;
        border: 1px solid rgba(0,255,157,0.1);
        transition: 0.3s;
    }
    .feature-item:hover { transform: translateY(-5px); border-color: var(--primary); box-shadow: var(--neon-glow); }
    .feature-icon {
        width: 70px;
        height: 70px;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        border-radius: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
        font-size: 30px;
        color: #0a192f;
    }
    .cta-section {
        text-align: center;
        padding: 80px 0;
    }
    .btn {
        padding: 14px 30px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        transition: 0.3s;
    }
    .btn-primary { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: #0a192f; }
    .btn-secondary { background: rgba(0,255,157,0.1); color: var(--primary); border: 2px solid var(--primary); }
    .btn:hover { transform: translateY(-3px); box-shadow: var(--neon-glow); }
    .login-banner {
        background: linear-gradient(135deg, rgba(108,99,255,0.2), rgba(0,255,157,0.1));
        border-left: 4px solid var(--primary);
        border-radius: 12px;
        padding: 15px 25px;
        margin: 30px 0;
        text-align: center;
        backdrop-filter: blur(5px);
    }
    .login-banner a {
        color: var(--primary);
        text-decoration: none;
        font-weight: bold;
    }
    .login-banner a:hover { text-decoration: underline; }
    @media (max-width: 768px) {
        .hero-title { font-size: 2.5rem; }
        .section-title { font-size: 2rem; }
    }
</style>

<div class="tech-grid"></div>
<div id="canvas3d"></div>
<div class="particles-container" id="particlesContainer"></div>

<section class="hero">
    <div class="container">
        <h1 class="hero-title">COMPILER VISUALIZER HUB</h1>
        <p class="hero-subtitle">Advanced 3D visualization platform for understanding compiler internals across multiple programming languages.</p>
        <div class="hero-typed" id="typed-text"></div>
        <div class="hero-stats">
            <div class="stat-item"><span class="stat-number" id="statLanguages">0</span><div class="stat-label">Languages & Tools</div></div>
            <div class="stat-item"><span class="stat-number" id="statVisualizations">20+</span><div class="stat-label">Visualization Modes</div></div>
            <div class="stat-item"><span class="stat-number" id="statUsers">1.2k+</span><div class="stat-label">Active Users</div></div>
        </div>
    </div>
</section>

<section id="languages" class="languages-section">
    <div class="container">
        <h2 class="section-title">Supported Languages & Tools</h2>
        
        <!-- Login encouragement banner -->
        <div class="login-banner">
            <i class="fas fa-lock" style="margin-right: 10px;"></i>
            <strong>Unlock full access:</strong> Some advanced languages and the interactive playground require you to 
            <?php if (!isset($_SESSION['user_id'])): ?>
                <a href="login.php">log in</a> or <a href="register.php">create an account</a>.
            <?php else: ?>
                <span style="color: var(--primary);">✅ You are logged in — enjoy all features!</span>
            <?php endif; ?>
            Login also enables saving visualizations and personalized learning paths.
        </div>
        
        <div class="languages-grid">
            <!-- Existing languages -->
            <div class="language-card"><div class="card-header"><div class="language-icon java"><i class="fab fa-java"></i></div><h3 class="language-title">Java</h3></div><div class="card-body"><p class="language-description">Full JVM pipeline visualization including bytecode generation, class loading, garbage collection, and JIT compilation.</p><a href="java.php" class="language-link"><i class="fas fa-rocket"></i> Launch Java Visualizer</a></div></div>
            <div class="language-card"><div class="card-header"><div class="language-icon c"><i class="fas fa-microchip"></i></div><h3 class="language-title">C</h3></div><div class="card-body"><p class="language-description">Direct compilation to assembly and machine code. Memory layout visualization, pointer operations, and preprocessor expansion.</p><a href="c.php" class="language-link"><i class="fas fa-rocket"></i> Launch C Visualizer</a></div></div>
            <div class="language-card"><div class="card-header"><div class="language-icon swift"><i class="fab fa-swift"></i></div><h3 class="language-title">Swift</h3></div><div class="card-body"><p class="language-description">Swift Intermediate Language (SIL) visualization, ARC optimization, protocol witness tables, and generic specialization.</p><a href="swift.php" class="language-link"><i class="fas fa-rocket"></i> Launch Swift Visualizer</a></div></div>
            <div class="language-card"><div class="card-header"><div class="language-icon brainfuck"><i class="fas fa-brain"></i></div><h3 class="language-title">Brainfuck</h3></div><div class="card-body"><p class="language-description">Minimalist language with tape memory visualization. Watch pointer movements, cell operations, and loop execution in real-time.</p><a href="brain-fuck.php" class="language-link"><i class="fas fa-rocket"></i> Launch Brainfuck Visualizer</a></div></div>
            <div class="language-card"><div class="card-header"><div class="language-icon go"><img src="https://raw.githubusercontent.com/golang-samples/gopher-vector/master/gopher.png" style="width: 32px; height: 32px; filter: brightness(0) invert(1);" alt="Go Gopher"></div><h3 class="language-title">Go</h3></div><div class="card-body"><p class="language-description">Fast compilation pipeline, goroutine scheduling, channel operations, and interface tables. Visualize Go's unique concurrency model.</p><a href="go.php" class="language-link"><i class="fas fa-rocket"></i> Launch Go Visualizer</a></div></div>
            <div class="language-card"><div class="card-header"><div class="language-icon python"><i class="fab fa-python"></i></div><h3 class="language-title">Python</h3></div><div class="card-body"><p class="language-description">Full CPython bytecode compilation pipeline, from parsing to code object generation. Visualize abstract syntax tree, symbol tables, and virtual machine execution.</p><a href="python.php" class="language-link"><i class="fas fa-rocket"></i> Launch Python Visualizer</a></div></div>
            <div class="language-card"><div class="card-header"><div class="language-icon javascript"><i class="fab fa-js"></i></div><h3 class="language-title">JavaScript</h3></div><div class="card-body"><p class="language-description">Modern JIT compilation pipeline (Ignition, Sparkplug, Maglev, TurboFan). Visualize bytecode, optimization feedback, and inline caching.</p><a href="javascript.php" class="language-link"><i class="fas fa-rocket"></i> Launch JavaScript Visualizer</a></div></div>
            <div class="language-card"><div class="card-header"><div class="language-icon php"><i class="fab fa-php"></i></div><h3 class="language-title">PHP</h3></div><div class="card-body"><p class="language-description">Zend Engine compilation: AST to opcodes, opcache integration, and runtime execution. Explore oparray structure and internal function calls.</p><a href="php.php" class="language-link"><i class="fas fa-rocket"></i> Launch PHP Visualizer</a></div></div>
            
            <!-- Newly added R language -->
            <div class="language-card"><div class="card-header"><div class="language-icon r"><i class="fab fa-r-project"></i></div><h3 class="language-title">R</h3></div><div class="card-body"><p class="language-description">R compiler and bytecode visualization. Explore S3/S4 object systems, lazy evaluation, and JIT-compiled bytecode loops.</p><a href="r.php" class="language-link"><i class="fas fa-chart-line"></i> Launch R Visualizer</a></div></div>
            
            <!-- Playground add‑on (not a compiler) with clarification -->
            <div class="language-card"><div class="card-header"><div class="language-icon playground"><i class="fas fa-laptop-code"></i></div><h3 class="language-title">Playground</h3></div><div class="card-body"><p class="language-description"><span style="background: var(--accent); padding: 2px 8px; border-radius: 20px; font-size: 0.8rem;">ADD‑ON</span><br><strong>Interactive HTML/CSS/JS environment</strong> — This is <em>not</em> a compiler visualizer. Experiment with front‑end code, see live preview, and test snippets. Great for learning web technologies.</p><a href="playground.php" class="language-link"><i class="fas fa-play"></i> Open Playground</a></div></div>
        </div>
    </div>
</section>

<section id="features" class="features-visualization">
    <div class="container">
        <h2 class="section-title">Advanced Features</h2>
        <div class="feature-grid">
            <div class="feature-item"><div class="feature-icon"><i class="fas fa-cube"></i></div><h3 class="feature-title">Interactive 3D</h3><p class="feature-description">Rotate, zoom, and explore compiler internals in three dimensions with real-time WebGL rendering.</p></div>
            <div class="feature-item"><div class="feature-icon"><i class="fas fa-code-branch"></i></div><h3 class="feature-title">Pipeline View</h3><p class="feature-description">Step through compilation stages with detailed explanations. Watch data flow between lexical, syntax, and semantic analysis.</p></div>
            <div class="feature-item"><div class="feature-icon"><i class="fas fa-project-diagram"></i></div><h3 class="feature-title">AST Explorer</h3><p class="feature-description">Interactive Abstract Syntax Trees with node highlighting, zoom, and expand/collapse.</p></div>
            <div class="feature-item"><div class="feature-icon"><i class="fas fa-memory"></i></div><h3 class="feature-title">Memory Models</h3><p class="feature-description">JVM heap, C stack frames, Swift ARC, and Brainfuck tape visualization.</p></div>
            <div class="feature-item"><div class="feature-icon"><i class="fas fa-download"></i></div><h3 class="feature-title">Export System</h3><p class="feature-description">Download generated ASTs, intermediate code, and 3D visualizations for offline study.</p></div>
            <div class="feature-item"><div class="feature-icon"><i class="fas fa-graduation-cap"></i></div><h3 class="feature-title">Educational</h3><p class="feature-description">Perfect for computer science education. Used by universities worldwide for compiler design courses.</p></div>
        </div>
    </div>
</section>

<section id="cta" class="cta-section">
    <div class="container">
        <div class="cta-container">
            <h2 class="cta-title">Start Exploring Now</h2>
            <p class="cta-subtitle">Whether you're a student learning compiler design, a developer optimizing code, or a researcher exploring language implementation, CompilerHub provides unparalleled insights.</p>
            <div class="cta-buttons">
                <a href="#languages" class="btn btn-primary"><i class="fas fa-rocket"></i> Explore All Languages</a>
                <a href="https://github.com/Agabaofficial/compiler-visualizer-hub" class="btn btn-secondary" target="_blank"><i class="fab fa-github"></i> View Source Code</a>
            </div>
        </div>
    </div>
</section>

<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/effects/OutlineEffect.js"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/postprocessing/EffectComposer.js"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/postprocessing/RenderPass.js"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/postprocessing/UnrealBloomPass.js"></script>
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script src="https://cdn.jsdelivr.net/npm/typed.js@2.0.12"></script>
<script>
    AOS.init({ duration: 1200, once: true, offset: 100 });
    new Typed('#typed-text', {
        strings: ['> Initializing compiler visualization engine...', '> Loading 3D rendering pipeline...', '> Connecting to language compilers...', '> Ready for interactive exploration.'],
        typeSpeed: 50, backSpeed: 30, loop: true, cursorChar: '_'
    });
    
    // Counter animation (now 10 languages/tools)
    function animateCounter(el, target, suffix='', duration=2000) {
        let start=0, inc=target/(duration/16);
        let timer=setInterval(()=>{
            start+=inc;
            if(start>=target){ el.textContent=target+suffix; clearInterval(timer); }
            else el.textContent=Math.floor(start)+suffix;
        },16);
    }
    const statsObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if(entry.isIntersecting){
                animateCounter(document.getElementById('statLanguages'),10);
                animateCounter(document.getElementById('statVisualizations'),20,'+');
                animateCounter(document.getElementById('statUsers'),1200,'+');
                statsObserver.unobserve(entry.target);
            }
        });
    }, { threshold:0.5 });
    statsObserver.observe(document.querySelector('.hero'));
    
    // 3D Background
    function initThreeJS() {
        const container = document.getElementById('canvas3d');
        const scene = new THREE.Scene();
        const camera = new THREE.PerspectiveCamera(75, window.innerWidth/window.innerHeight, 0.1, 1000);
        const renderer = new THREE.WebGLRenderer({ alpha: true, antialias: true });
        renderer.setSize(window.innerWidth, window.innerHeight);
        renderer.setClearColor(0x000000,0);
        container.appendChild(renderer.domElement);
        
        const composer = new THREE.EffectComposer(renderer);
        const renderPass = new THREE.RenderPass(scene, camera);
        composer.addPass(renderPass);
        const bloomPass = new THREE.UnrealBloomPass(new THREE.Vector2(window.innerWidth, window.innerHeight), 0.5, 0.4, 0.85);
        composer.addPass(bloomPass);
        
        const colors = [0x00ff9d, 0xA8B9CC, 0xFA7343, 0x2C3E50, 0x00ADD8];
        const geometries = [new THREE.IcosahedronGeometry(2,1), new THREE.TetrahedronGeometry(2,1), new THREE.DodecahedronGeometry(2,0), new THREE.BoxGeometry(2,2,2), new THREE.SphereGeometry(2,16,16)];
        const nodes = [];
        for(let i=0;i<5;i++){
            const mat = new THREE.MeshPhongMaterial({ color: colors[i], emissive: colors[i], emissiveIntensity:0.3, transparent:true, opacity:0.7 });
            const node = new THREE.Mesh(geometries[i], mat);
            const angle=(i/5)*Math.PI*2;
            node.position.x=Math.cos(angle)*15;
            node.position.z=Math.sin(angle)*15;
            node.position.y=(i-2)*4;
            node.userData={originalY:node.position.y, speed:0.5+Math.random()*0.5, angle:angle, radius:15};
            scene.add(node);
            nodes.push(node);
        }
        const processorGeo = new THREE.TorusKnotGeometry(3,1,100,16);
        const processorMat = new THREE.MeshPhongMaterial({ color:0x6c63ff, emissive:0x6c63ff, emissiveIntensity:0.5 });
        const processor = new THREE.Mesh(processorGeo, processorMat);
        scene.add(processor);
        
        const ambientLight = new THREE.AmbientLight(0x404040,0.5);
        scene.add(ambientLight);
        const dirLight = new THREE.DirectionalLight(0xffffff,0.8);
        dirLight.position.set(10,20,15);
        scene.add(dirLight);
        const pointLight = new THREE.PointLight(0x00ff9d,1,100);
        pointLight.position.set(0,0,0);
        scene.add(pointLight);
        
        camera.position.set(0,10,40);
        let time=0;
        function animate(){
            requestAnimationFrame(animate);
            time+=0.01;
            nodes.forEach((node,i)=>{
                node.userData.angle+=0.002*node.userData.speed;
                node.position.x=Math.cos(node.userData.angle+time)*node.userData.radius;
                node.position.z=Math.sin(node.userData.angle+time)*node.userData.radius;
                node.position.y=node.userData.originalY+Math.sin(time*0.5+i)*3;
                node.rotation.x+=0.01*node.userData.speed;
                node.rotation.y+=0.01*node.userData.speed;
            });
            processor.rotation.x+=0.01;
            processor.rotation.y+=0.01;
            processor.scale.setScalar(1+Math.sin(time)*0.1);
            pointLight.position.x=Math.sin(time*0.5)*20;
            pointLight.position.z=Math.cos(time*0.5)*20;
            camera.position.x=Math.sin(time*0.05)*40;
            camera.position.z=Math.cos(time*0.05)*40;
            camera.lookAt(0,0,0);
            composer.render();
        }
        animate();
        window.addEventListener('resize',()=>{
            camera.aspect=window.innerWidth/window.innerHeight;
            camera.updateProjectionMatrix();
            renderer.setSize(window.innerWidth,window.innerHeight);
            composer.setSize(window.innerWidth,window.innerHeight);
        });
    }
    function createParticles(){
        const container=document.getElementById('particlesContainer');
        for(let i=0;i<80;i++){
            const p=document.createElement('div');
            p.className='particle';
            const size=Math.random()*4+1;
            p.style.width=`${size}px`;
            p.style.height=`${size}px`;
            p.style.left=`${Math.random()*100}%`;
            p.style.top=`${Math.random()*100}%`;
            p.style.background=Math.random()>0.7?'#6c63ff':Math.random()>0.5?'#ff2e63':'#00ff9d';
            p.style.opacity=Math.random()*0.5+0.1;
            const dur=Math.random()*20+10, delay=Math.random()*5;
            p.style.animation=`float ${dur}s ease-in-out ${delay}s infinite`;
            container.appendChild(p);
        }
        const style=document.createElement('style');
        style.textContent=`
            @keyframes float{
                0%,100%{transform:translate(0,0) rotate(0deg);}
                25%{transform:translate(${Math.random()*100-50}px,${Math.random()*100-50}px) rotate(90deg);}
                50%{transform:translate(${Math.random()*100-50}px,${Math.random()*100-50}px) rotate(180deg);}
                75%{transform:translate(${Math.random()*100-50}px,${Math.random()*100-50}px) rotate(270deg);}
            }
        `;
        document.head.appendChild(style);
    }
    window.addEventListener('load', ()=>{ initThreeJS(); createParticles(); });
</script>

<?php include 'includes/footer.php'; ?>