document.addEventListener('DOMContentLoaded', function() {
    VANTA.NET({
        el: "#hero-animation",
        mouseControls: true,
        touchControls: true,
        gyroControls: false,
        minHeight: 200.00,
        minWidth: 200.00,
        scale: 1.00,
        scaleMobile: 1.00,
        color: [0, 0, 0],
        backgroundColor: [255, 255, 255]
    });

    const registerButton = document.querySelector('.login-button');

    if (registerButton) {
        // Contoh animasi GSAP
        gsap.from(registerButton, { duration: 0, scale: 0, opacity: 0 });
    } else {
        console.error('Register button not found!');
    }
});

// Inisialisasi animasi background VANTA
VANTA.NET({
    el: "#vanta-background",
    mouseControls: true,
    touchControls: true,
    gyroControls: false,
    minHeight: 200.00,
    minWidth: 200.00,
    scale: 1.00,
    scaleMobile: 1.00,
    color: 0xe63946,
    backgroundColor: 0x1a1a1a,
    points: 15.00,
    maxDistance: 25.00,
    spacing: 17.00,
    showDots: false
});

// Animasi dengan GSAP
document.addEventListener('DOMContentLoaded', () => {
    // Animasi login container
    gsap.from(".login-container", {
        duration: 1.5,
        y: 100,
        opacity: 0,
        ease: "power4.out"
    });

    // Animasi header
    gsap.from(".login-header", {
        duration: 1,
        scale: 0.5,
        opacity: 0,
        delay: 0.5,
        ease: "back.out(1.7)"
    });

    // Animasi form groups
    gsap.from(".form-group", {
        duration: 0.8,
        x: -100,
        opacity: 0,
        stagger: 0.2,
        delay: 1,
        ease: "power2.out"
    });

    // Animasi button
    gsap.from(".login-button", {
        duration: 1,
        scale: 0,
        opacity: 0,
        delay: 1.5,
        ease: "elastic.out(1, 0.3)"
    });
});

// Efek hover 3D
const container = document.querySelector('.login-container');
container.addEventListener('mousemove', (e) => {
    const { left, top, width, height } = container.getBoundingClientRect();
    const x = (e.clientX - left) / width;
    const y = (e.clientY - top) / height;
    
    const rotateX = 20 * (y - 0.5);
    const rotateY = 20 * (0.5 - x);
    
    gsap.to(container, {
        duration: 0.5,
        rotateX: rotateX,
        rotateY: rotateY,
        transformPerspective: 1000,
        ease: "power2.out"
    });
});

container.addEventListener('mouseleave', () => {
    gsap.to(container, {
        duration: 1,
        rotateX: 0,
        rotateY: 0,
        ease: "elastic.out(1, 0.3)"
    });
}); 