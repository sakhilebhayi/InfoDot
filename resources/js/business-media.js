// R-29 business media: pick a logo or cover, see it inside the real frame, drag to place it and
// zoom to crop tighter. The frame computes a fractional crop rectangle (x, y, w, h of the source
// image) that the server applies with GD — nothing is cropped client-side. Without JavaScript the
// form still posts and the server centre-crops, so this is an enhancement, not a requirement.

function enhance(form) {
    const aspect = parseFloat(form.dataset.cropAspect || '1');
    const file = form.querySelector('[data-crop-file]');
    const frame = form.querySelector('[data-crop-frame]');
    const img = form.querySelector('[data-crop-img]');
    const zoom = form.querySelector('[data-crop-zoom]');

    if (!file || !frame || !img || !zoom) return;

    // The picture's natural size, the frame's box, and the current placement.
    let natural = null; // { w, h }
    let state = { scale: 1, x: 0, y: 0 }; // translation of the image centre from the frame centre, in frame pixels
    let url = null;

    const window_ = frame.querySelector('.ix-crop__window');

    function cover() {
        // The scale at which the image exactly covers the frame at zoom 1.
        const fw = window_.clientWidth, fh = window_.clientHeight;
        return Math.max(fw / natural.w, fh / natural.h);
    }

    function clamp() {
        const fw = window_.clientWidth, fh = window_.clientHeight;
        const s = cover() * state.scale;
        const maxX = Math.max(0, (natural.w * s - fw) / 2);
        const maxY = Math.max(0, (natural.h * s - fh) / 2);
        state.x = Math.min(maxX, Math.max(-maxX, state.x));
        state.y = Math.min(maxY, Math.max(-maxY, state.y));
    }

    function paint() {
        if (!natural) return;
        clamp();
        const s = cover() * state.scale;
        img.style.width = `${natural.w * s}px`;
        img.style.height = `${natural.h * s}px`;
        img.style.transform = `translate(calc(-50% + ${state.x}px), calc(-50% + ${state.y}px))`;
        write();
    }

    function write() {
        // Fractional source window currently visible in the frame.
        const fw = window_.clientWidth, fh = window_.clientHeight;
        const s = cover() * state.scale;
        const w = Math.min(1, fw / (natural.w * s));
        const h = Math.min(1, fh / (natural.h * s));
        const cx = 0.5 - state.x / (natural.w * s) - w / 2;
        const cy = 0.5 - state.y / (natural.h * s) - h / 2;

        for (const [k, v] of [['x', cx], ['y', cy], ['w', w], ['h', h]]) {
            let input = form.querySelector(`input[name="crop[${k}]"]`);
            if (!input) {
                input = document.createElement('input');
                input.type = 'hidden';
                input.name = `crop[${k}]`;
                form.appendChild(input);
            }
            input.value = Math.min(1, Math.max(0, v)).toFixed(4);
        }
    }

    file.addEventListener('change', () => {
        const picked = file.files && file.files[0];
        if (!picked || !picked.type.startsWith('image/')) { frame.hidden = true; return; }
        if (url) URL.revokeObjectURL(url);
        url = URL.createObjectURL(picked);
        img.onload = () => {
            natural = { w: img.naturalWidth, h: img.naturalHeight };
            state = { scale: 1, x: 0, y: 0 };
            zoom.value = '1';
            frame.hidden = false;
            paint();
        };
        img.src = url;
    });

    zoom.addEventListener('input', () => {
        if (!natural) return;
        state.scale = parseFloat(zoom.value) || 1;
        paint();
    });

    let drag = null;
    window_.addEventListener('pointerdown', (e) => {
        if (!natural) return;
        drag = { x: e.clientX - state.x, y: e.clientY - state.y };
        window_.setPointerCapture(e.pointerId);
        e.preventDefault();
    });
    window_.addEventListener('pointermove', (e) => {
        if (!drag) return;
        state.x = e.clientX - drag.x;
        state.y = e.clientY - drag.y;
        paint();
    });
    const drop = () => { drag = null; };
    window_.addEventListener('pointerup', drop);
    window_.addEventListener('pointercancel', drop);

    // A resize changes the frame's pixel box, so the placement maths must rerun.
    window.addEventListener('resize', () => { if (!frame.hidden) paint(); });
}

document.querySelectorAll('form[data-crop-form]').forEach(enhance);
