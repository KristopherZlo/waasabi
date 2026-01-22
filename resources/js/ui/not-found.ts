let notFoundCleanup: (() => void) | null = null;

export const setupNotFoundPage = () => {
    if (notFoundCleanup) {
        notFoundCleanup();
        notFoundCleanup = null;
    }

    const root = document.querySelector<HTMLElement>('[data-not-found]');
    if (!root) {
        return;
    }

    const orbitEl = root.querySelector<HTMLElement>('[data-not-found-orbit]');
    const boxEl = root.querySelector<HTMLElement>('[data-not-found-box]');
    const starsEl = root.querySelector<HTMLElement>('[data-not-found-stars]');
    const scoreEl = root.querySelector<HTMLElement>('[data-not-found-score]');
    const noteEl = root.querySelector<HTMLElement>('[data-not-found-note]');
    const retryEl = root.querySelector<HTMLButtonElement>('[data-not-found-retry]');
    const planetEl = root.querySelector<HTMLElement>('.not-found__planet');
    const planetWrap = root.querySelector<HTMLElement>('.not-found__planet-wrap');
    const starSrc = root.dataset.notFoundStarSrc ?? root.dataset.starSrc ?? '';

    if (!orbitEl || !boxEl || !starsEl || !scoreEl || !planetEl || !planetWrap || !starSrc) {
        return;
    }

    let baseScore = Number(scoreEl.dataset.notFoundBase ?? scoreEl.dataset.base ?? scoreEl.textContent ?? '404');
    if (!Number.isFinite(baseScore)) {
        baseScore = 404;
    }
    const baseCode = Math.max(0, Math.floor(baseScore));
    const baseTail = baseCode % 100;
    let health = Math.min(9, Math.max(0, Math.floor(baseCode / 100)));
    let score = 0;
    let isGameOver = false;
    let isWin = false;
    let boxFlightFrame = 0;
    let boxHitStart = 0;
    const boxHitDuration = 260;
    let boxHitData: Uint8ClampedArray | null = null;
    let boxHitWidth = 0;
    let boxHitHeight = 0;
    const boxHitAlphaThreshold = 32;

    const renderScore = (value: string) => {
        const existingDigits = Array.from(scoreEl.querySelectorAll<HTMLSpanElement>('.not-found__digit'));
        let digits = existingDigits;
        if (existingDigits.length !== value.length) {
            scoreEl.innerHTML = '';
            digits = value.split('').map((char, index) => {
                const span = document.createElement('span');
                span.className = 'not-found__digit';
                span.textContent = char;
                span.style.setProperty('--float-delay', `${-0.3 * index - Math.random()}s`);
                span.style.setProperty('--float-duration', `${5 + Math.random() * 3.5}s`);
                span.style.setProperty('--float-offset', `${6 + Math.random() * 10}px`);
                span.style.setProperty('--float-x', `${(Math.random() * 10 - 5).toFixed(2)}px`);
                scoreEl.appendChild(span);
                return span;
            });
        } else {
            value.split('').forEach((char, index) => {
                const span = digits[index];
                if (span) {
                    span.textContent = char;
                }
            });
        }
    };

    const defaultNote = noteEl?.textContent ?? '';
    const winNote = noteEl?.dataset.notFoundNoteWin ?? defaultNote;

    const updateScore = () => {
        const tailValue = (baseTail + score) % 100;
        const value = `${health}${String(tailValue).padStart(2, '0')}`;
        renderScore(value);
        if (!isWin && tailValue >= 99) {
            triggerWin();
        }
    };

    updateScore();

    let rootRect = root.getBoundingClientRect();
    let orbitRect = orbitEl.getBoundingClientRect();
    let radius = orbitRect.width / 2;
    let orbitCenter = { x: radius, y: radius };
    let orbitCenterAbs = { x: orbitRect.left + radius, y: orbitRect.top + radius };

    const refreshGeometry = () => {
        rootRect = root.getBoundingClientRect();
        orbitRect = orbitEl.getBoundingClientRect();
        const planetRect = planetEl.getBoundingClientRect();
        const nextRadius = planetRect.width / 2;
        if (Number.isFinite(nextRadius) && nextRadius > 0) {
            radius = nextRadius;
            orbitCenterAbs = {
                x: planetRect.left + radius,
                y: planetRect.top + radius,
            };
        } else {
            radius = orbitRect.width / 2;
            orbitCenterAbs = {
                x: orbitRect.left + radius,
                y: orbitRect.top + radius,
            };
        }
        orbitCenter = {
            x: orbitCenterAbs.x - orbitRect.left,
            y: orbitCenterAbs.y - orbitRect.top,
        };
    };

    refreshGeometry();

    const prepareBoxHitMap = () => {
        if (boxHitData) {
            return;
        }
        if (!(boxEl instanceof HTMLImageElement)) {
            return;
        }
        if (!boxEl.complete || !boxEl.naturalWidth || !boxEl.naturalHeight) {
            return;
        }
        const canvas = document.createElement('canvas');
        canvas.width = boxEl.naturalWidth;
        canvas.height = boxEl.naturalHeight;
        const ctx = canvas.getContext('2d');
        if (!ctx) {
            return;
        }
        ctx.drawImage(boxEl, 0, 0);
        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        boxHitData = imageData.data;
        boxHitWidth = canvas.width;
        boxHitHeight = canvas.height;
    };

    if (boxEl instanceof HTMLImageElement) {
        if (boxEl.complete) {
            prepareBoxHitMap();
        } else {
            boxEl.addEventListener('load', prepareBoxHitMap, { once: true });
        }
    }

    const clamp = (value: number, min: number, max: number) => Math.min(max, Math.max(min, value));

    let targetAngle = Math.PI / 2;
    let currentAngle = targetAngle;

    const updateTargetFromPointer = (clientX: number, clientY: number) => {
        const centerX = orbitCenterAbs.x;
        const centerY = orbitCenterAbs.y;
        const dx = clientX - centerX;
        const dy = Math.abs(centerY - clientY);
        const angle = Math.atan2(dy, dx);
        targetAngle = clamp(angle, 0, Math.PI);
    };

    const positionBox = (now: number) => {
        const boxRadius = radius + 35;
        const x = orbitCenter.x + boxRadius * Math.cos(currentAngle);
        const y = orbitCenter.y - boxRadius * Math.sin(currentAngle);
        const rotation = (Math.PI / 2 - currentAngle) * (180 / Math.PI);
        let bounceOffset = 0;
        let scaleX = 1;
        let scaleY = 1;
        if (boxHitStart) {
            const elapsed = now - boxHitStart;
            if (elapsed >= boxHitDuration) {
                boxHitStart = 0;
            } else {
                const phase = Math.sin(Math.PI * (elapsed / boxHitDuration));
                bounceOffset = phase * 10;
                scaleY = 1 - phase * 0.12;
                scaleX = 1 + phase * 0.05;
            }
        }
        boxEl.style.left = `${x}px`;
        boxEl.style.top = `${y}px`;
        boxEl.style.transform = `translate(-50%, -50%) translateY(var(--box-offset)) rotate(${rotation.toFixed(2)}deg) translateY(${bounceOffset.toFixed(2)}px) scale(${scaleX.toFixed(3)}, ${scaleY.toFixed(3)})`;
    };

    let boxRaf = 0;
    const animateBox = (now: number) => {
        currentAngle += (targetAngle - currentAngle) * 0.12;
        positionBox(now);
        boxRaf = window.requestAnimationFrame(animateBox);
    };

    positionBox(performance.now());
    boxRaf = window.requestAnimationFrame(animateBox);

    const triggerBoxHit = () => {
        boxHitStart = performance.now();
    };

    const isStarHittingBox = (starAbsX: number, starAbsY: number, starRadius: number) => {
        const boxRect = boxEl.getBoundingClientRect();
        if (
            starAbsX + starRadius < boxRect.left ||
            starAbsX - starRadius > boxRect.right ||
            starAbsY + starRadius < boxRect.top ||
            starAbsY - starRadius > boxRect.bottom
        ) {
            return false;
        }

        const boxCenterX = boxRect.left + boxRect.width / 2;
        const boxCenterY = boxRect.top + boxRect.height / 2;
        const rotation = Math.PI / 2 - currentAngle;
        const cos = Math.cos(-rotation);
        const sin = Math.sin(-rotation);
        const boxWidth = boxEl.offsetWidth || boxRect.width;
        const boxHeight = boxEl.offsetHeight || boxRect.height;
        const halfW = boxWidth / 2;
        const halfH = boxHeight / 2;
        const fallbackInsetX = boxWidth * 0.16;
        const fallbackInsetY = boxHeight * 0.16;
        const scaleX = boxHitWidth && boxHitData ? boxHitWidth / boxWidth : 1;
        const scaleY = boxHitHeight && boxHitData ? boxHitHeight / boxHeight : 1;
        const sampleOffset = Math.max(2, starRadius * 0.45);
        const samples: Array<[number, number]> = [
            [0, 0],
            [sampleOffset, 0],
            [-sampleOffset, 0],
            [0, sampleOffset],
            [0, -sampleOffset],
        ];

        for (const [offsetX, offsetY] of samples) {
            const dx = starAbsX + offsetX - boxCenterX;
            const dy = starAbsY + offsetY - boxCenterY;
            const localX = dx * cos - dy * sin;
            const localY = dx * sin + dy * cos;
            const x = localX + halfW;
            const y = localY + halfH;
            if (x < 0 || y < 0 || x >= boxWidth || y >= boxHeight) {
                continue;
            }
            if (boxHitData && boxHitWidth && boxHitHeight) {
                const imgX = Math.min(boxHitWidth - 1, Math.max(0, Math.floor(x * scaleX)));
                const imgY = Math.min(boxHitHeight - 1, Math.max(0, Math.floor(y * scaleY)));
                const alpha = boxHitData[(imgY * boxHitWidth + imgX) * 4 + 3];
                if (alpha > boxHitAlphaThreshold) {
                    return true;
                }
            } else {
                if (
                    x > fallbackInsetX &&
                    x < boxWidth - fallbackInsetX &&
                    y > fallbackInsetY &&
                    y < boxHeight - fallbackInsetY
                ) {
                    return true;
                }
            }
        }

        return false;
    };

    const onMouseMove = (event: MouseEvent) => updateTargetFromPointer(event.clientX, event.clientY);
    const onTouchMove = (event: TouchEvent) => {
        const touch = event.touches[0];
        if (touch) {
            updateTargetFromPointer(touch.clientX, touch.clientY);
        }
    };

    window.addEventListener('mousemove', onMouseMove, { passive: true });
    window.addEventListener('touchmove', onTouchMove, { passive: true });
    window.addEventListener('resize', refreshGeometry);

    let spawnTimeout: number | undefined;
    let starAnimationFrame = 0;
    let growAnimationFrame = 0;
    let activeStar: HTMLImageElement | null = null;
    let trailEl: HTMLDivElement | null = null;
    let trailDots: HTMLSpanElement[] = [];
    let trailPositions: Array<{ x: number; y: number }> = [];
    let starSpeedMultiplier = 1;
    let planetHitTimeout: number | undefined;
    let winStarsBuilt = false;
    let winStarsEl: HTMLDivElement | null = null;
    const firstStarDelay = 5000;
    const pageStart = performance.now();
    let firstStarPending = true;

    const triggerPlanetHit = () => {
        planetWrap.classList.remove('is-hit');
        void planetWrap.offsetWidth;
        planetWrap.classList.add('is-hit');
        window.clearTimeout(planetHitTimeout);
        planetHitTimeout = window.setTimeout(() => {
            planetWrap.classList.remove('is-hit');
        }, 700);
    };

    const triggerPlanetDestroyed = () => {
        if (isGameOver) {
            return;
        }
        isGameOver = true;
        root.classList.add('is-game-over');
        boxHitStart = 0;
        planetWrap.classList.remove('is-hit');
        planetWrap.classList.add('is-destroyed');
        window.clearTimeout(planetHitTimeout);
        planetHitTimeout = window.setTimeout(() => {
            planetWrap.classList.remove('is-destroyed');
            planetWrap.style.display = 'none';
        }, 700);

        window.removeEventListener('mousemove', onMouseMove);
        window.removeEventListener('touchmove', onTouchMove);
        if (boxRaf) {
            window.cancelAnimationFrame(boxRaf);
            boxRaf = 0;
        }

        startBoxFlyaway({ scaleDown: false });
    };

    const startBoxFlyaway = ({ scaleDown }: { scaleDown: boolean }) => {
        rootRect = root.getBoundingClientRect();
        const boxRect = boxEl.getBoundingClientRect();
        const startX = boxRect.left - rootRect.left + boxRect.width / 2;
        const startY = boxRect.top - rootRect.top + boxRect.height / 2;
        const dirX = Math.random() * 2 - 1;
        const dirY = 0.9 + Math.random() * 0.9;
        const dirLength = Math.hypot(dirX, dirY) || 1;
        const travelDistance =
            Math.max(rootRect.width, rootRect.height) * (0.9 + Math.random() * 0.7);
        const travelX = (dirX / dirLength) * travelDistance;
        const travelY = -Math.abs((dirY / dirLength) * travelDistance);
        const spinSpeed = (90 + Math.random() * 160) * (Math.random() < 0.5 ? -1 : 1);
        const flightDuration = 18000 + Math.random() * 12000;
        const flightStart = performance.now();
        const startScale = 1;
        const endScale = scaleDown ? 0.35 : 1;
        let angle = 0;
        root.appendChild(boxEl);
        boxEl.style.left = `${startX}px`;
        boxEl.style.top = `${startY}px`;
        boxEl.style.zIndex = '5';
        boxEl.style.transform = `translate(-50%, -50%) rotate(${angle}deg) scale(${startScale})`;

        const animateFly = (now: number) => {
            const progress = Math.min(1, (now - flightStart) / flightDuration);
            const eased = 1 - Math.pow(1 - progress, 2);
            const posX = startX + travelX * eased;
            const posY = startY + travelY * eased;
            const scale = startScale + (endScale - startScale) * eased;
            angle = spinSpeed * ((now - flightStart) / 1000);
            boxEl.style.left = `${posX}px`;
            boxEl.style.top = `${posY}px`;
            boxEl.style.transform = `translate(-50%, -50%) rotate(${angle.toFixed(2)}deg) scale(${scale.toFixed(3)})`;
            if (
                posX < -200 ||
                posX > rootRect.width + 200 ||
                posY < -200 ||
                posY > rootRect.height + 200
            ) {
                boxEl.remove();
                return;
            }
            if (progress < 1) {
                boxFlightFrame = window.requestAnimationFrame(animateFly);
            }
        };

        boxFlightFrame = window.requestAnimationFrame(animateFly);
    };

    const buildWinStars = () => {
        if (winStarsBuilt) {
            return;
        }
        winStarsBuilt = true;
        winStarsEl = document.createElement('div');
        winStarsEl.className = 'not-found__win-stars';
        refreshGeometry();
        const area = rootRect.width * rootRect.height;
        const count = Math.max(35, Math.min(90, Math.round(area / 14000)));
        const fragment = document.createDocumentFragment();
        for (let i = 0; i < count; i += 1) {
            const star = document.createElement('span');
            star.className = 'not-found__bg-star';
            const size = 2 + Math.random() * 5.5;
            star.style.width = `${size.toFixed(2)}px`;
            star.style.height = `${size.toFixed(2)}px`;
            star.style.left = `${(Math.random() * 100).toFixed(2)}%`;
            star.style.top = `${(Math.random() * 100).toFixed(2)}%`;
            star.style.setProperty(
                '--win-star-opacity',
                `${(0.35 + Math.random() * 0.65).toFixed(2)}`,
            );
            star.style.setProperty('--win-flicker-delay', `${(-Math.random() * 6).toFixed(2)}s`);
            star.style.setProperty(
                '--win-flicker-duration',
                `${(2.5 + Math.random() * 4.5).toFixed(2)}s`,
            );
            star.style.setProperty('--win-star-delay', `${(Math.random() * 0.8).toFixed(2)}s`);
            fragment.appendChild(star);
        }
        winStarsEl.appendChild(fragment);
        starsEl.appendChild(winStarsEl);
    };

    const triggerWin = () => {
        if (isWin || isGameOver) {
            return;
        }
        isWin = true;
        root.classList.add('is-won');
        window.clearTimeout(spawnTimeout);
        if (starAnimationFrame) {
            window.cancelAnimationFrame(starAnimationFrame);
            starAnimationFrame = 0;
        }
        if (growAnimationFrame) {
            window.cancelAnimationFrame(growAnimationFrame);
            growAnimationFrame = 0;
        }
        removeStar();
        window.removeEventListener('mousemove', onMouseMove);
        window.removeEventListener('touchmove', onTouchMove);
        if (boxRaf) {
            window.cancelAnimationFrame(boxRaf);
            boxRaf = 0;
        }
        startBoxFlyaway({ scaleDown: true });
        buildWinStars();
        if (noteEl && !noteEl.dataset.winApplied) {
            noteEl.textContent = winNote;
            noteEl.dataset.winApplied = '1';
        }
    };

    const removeStar = () => {
        if (activeStar) {
            activeStar.remove();
            activeStar = null;
        }
        if (trailEl) {
            trailEl.remove();
            trailEl = null;
            trailDots = [];
            trailPositions = [];
        }
        if (starAnimationFrame) {
            window.cancelAnimationFrame(starAnimationFrame);
            starAnimationFrame = 0;
        }
        if (growAnimationFrame) {
            window.cancelAnimationFrame(growAnimationFrame);
            growAnimationFrame = 0;
        }
    };

    const buildTrail = () => {
        if (trailEl) {
            return;
        }
        trailEl = document.createElement('div');
        trailEl.className = 'not-found__trail';
        trailDots = [];
        trailPositions = [];
        for (let i = 0; i < 30; i += 1) {
            const dot = document.createElement('span');
            dot.className = 'not-found__trail-dot';
            trailEl.appendChild(dot);
            trailDots.push(dot);
        }
        starsEl.appendChild(trailEl);
    };

    const updateTrail = (x: number, y: number, strength = 1) => {
        if (!trailDots.length) {
            return;
        }
        const normalized = Math.min(1, Math.max(0, strength));
        trailPositions.unshift({ x, y });
        if (trailPositions.length > trailDots.length) {
            trailPositions.pop();
        }
        trailDots.forEach((dot, index) => {
            const pos = trailPositions[index];
            if (!pos) {
                dot.style.opacity = '0';
                return;
            }
            const falloff = (trailDots.length - index) / trailDots.length;
            const power = falloff * normalized;
            dot.style.left = `${pos.x}px`;
            dot.style.top = `${pos.y}px`;
            dot.style.opacity = `${0.45 * power}`;
            dot.style.transform = `translate(-50%, -50%) scale(${0.35 + power * 0.55})`;
        });
    };

    const scheduleStar = () => {
        if (isGameOver || isWin) {
            return;
        }
        window.clearTimeout(spawnTimeout);
        const baseDelay = (1200 + Math.random() * 1800) / starSpeedMultiplier;
        const delay = firstStarPending
            ? Math.max(0, firstStarDelay - (performance.now() - pageStart))
            : baseDelay;
        spawnTimeout = window.setTimeout(() => {
            if (!document.body.contains(root)) {
                return;
            }
            if (document.visibilityState === 'hidden') {
                scheduleStar();
                return;
            }
            if (activeStar) {
                scheduleStar();
                return;
            }
            spawnStar();
        }, delay);
    };

    const spawnStar = () => {
        if (isGameOver || isWin) {
            return;
        }
        firstStarPending = false;
        refreshGeometry();
        const startX = rootRect.width * (0.1 + Math.random() * 0.8);
        const startY = rootRect.height * (0.08 + Math.random() * 0.35);

        starSpeedMultiplier = Math.min(3.5, starSpeedMultiplier + 0.08);

        const star = document.createElement('img');
        star.src = starSrc;
        star.alt = '';
        star.setAttribute('aria-hidden', 'true');
        star.className = 'not-found__star';
        star.style.left = `${startX}px`;
        star.style.top = `${startY}px`;
        const baseRotation = Math.random() * 360;
        const spinSpeed = (20 + Math.random() * 50) * (Math.random() < 0.5 ? -1 : 1);
        star.dataset.baseRotation = baseRotation.toFixed(2);
        star.dataset.spinSpeed = spinSpeed.toFixed(2);
        star.style.setProperty('--star-rotation', `${baseRotation}deg`);
        star.style.setProperty('--star-scale', '0.01');
        star.style.opacity = '0';
        star.style.animationDelay = `${Math.random() * 1.2}s`;
        star.style.animationDuration = `${2.2 + Math.random() * 1.8}s`;
        starsEl.appendChild(star);
        activeStar = star;

        const growDuration = 1200 / starSpeedMultiplier;
        buildTrail();
        updateTrail(startX, startY, 0.2);

        window.requestAnimationFrame(() => {
            star.classList.add('is-growing');
        });

        const growStart = performance.now();
        const animateGrow = (now: number) => {
            if (!activeStar) {
                return;
            }
            const progress = Math.min(1, (now - growStart) / growDuration);
            const eased = progress * (2 - progress);
            const scale = 0.01 + 0.99 * eased;
            const opacity = eased;
            updateTrail(startX, startY, 0.2 + eased * 0.5);
            const base = Number(star.dataset.baseRotation ?? 0);
            const spin = Number(star.dataset.spinSpeed ?? 0) * 0.35;
            const spinRotation = base + spin * ((now - growStart) / 1000);
            star.style.setProperty('--star-rotation', `${spinRotation}deg`);
            star.style.setProperty('--star-scale', scale.toFixed(3));
            star.style.opacity = opacity.toFixed(3);
            if (progress >= 1) {
                star.style.setProperty('--star-scale', '1');
                star.style.opacity = '1';
            }
            if (progress < 1) {
                growAnimationFrame = window.requestAnimationFrame(animateGrow);
            }
        };
        growAnimationFrame = window.requestAnimationFrame(animateGrow);

        window.setTimeout(() => {
            if (!activeStar) {
                return;
            }
            star.classList.add('is-falling');
            if (growAnimationFrame) {
                window.cancelAnimationFrame(growAnimationFrame);
                growAnimationFrame = 0;
            }
            star.style.opacity = '1';
            const currentRotation = Number.parseFloat(star.style.getPropertyValue('--star-rotation'));
            star.dataset.spinBase = Number.isFinite(currentRotation)
                ? currentRotation.toFixed(2)
                : (star.dataset.baseRotation ?? '0');
            const targetX = orbitCenterAbs.x - rootRect.left;
            const targetY = orbitCenterAbs.y - rootRect.top;
            const fallDuration = (1800 + Math.random() * 700) / starSpeedMultiplier;
            const startTime = performance.now();

            const animateFall = (now: number) => {
                if (!activeStar) {
                    return;
                }
                const progress = Math.min(1, (now - startTime) / fallDuration);
                const eased = progress * progress;
                const x = startX + (targetX - startX) * eased;
                const y = startY + (targetY - startY) * eased;
                const scale = 1 - eased * 0.25;
                const base = Number(star.dataset.spinBase ?? star.dataset.baseRotation ?? 0);
                const spin = Number(star.dataset.spinSpeed ?? 0);
                const spinRotation = base + spin * ((now - startTime) / 1000);
                star.style.left = `${x}px`;
                star.style.top = `${y}px`;
                star.style.setProperty('--star-scale', scale.toFixed(3));
                star.style.setProperty('--star-rotation', `${spinRotation}deg`);

                updateTrail(x, y, 1);

                const starRect = star.getBoundingClientRect();
                const starCenterAbsX = rootRect.left + x;
                const starCenterAbsY = rootRect.top + y;
                const starRadius = starRect.width * 0.5;
                const hit = isStarHittingBox(starCenterAbsX, starCenterAbsY, starRadius);

                if (hit) {
                    score += 1;
                    updateScore();
                    triggerBoxHit();
                    removeStar();
                    scheduleStar();
                    return;
                }

                const dx = starCenterAbsX - orbitCenterAbs.x;
                const dy = starCenterAbsY - orbitCenterAbs.y;
                const hitPlanet = Math.hypot(dx, dy) <= radius + starRadius;

                if (hitPlanet) {
                    if (health > 0) {
                        health = Math.max(0, health - 1);
                        updateScore();
                        triggerPlanetHit();
                    }
                    if (health <= 0) {
                        triggerPlanetDestroyed();
                    }
                    removeStar();
                    scheduleStar();
                    return;
                }

                if (progress < 1) {
                    starAnimationFrame = window.requestAnimationFrame(animateFall);
                    return;
                }

                removeStar();
                scheduleStar();
            };

            starAnimationFrame = window.requestAnimationFrame(animateFall);
        }, growDuration);
    };

    scheduleStar();

    const handleRetry = () => {
        window.location.reload();
    };

    if (retryEl) {
        retryEl.addEventListener('click', handleRetry);
    }

    notFoundCleanup = () => {
        window.cancelAnimationFrame(boxRaf);
        window.cancelAnimationFrame(starAnimationFrame);
        window.cancelAnimationFrame(boxFlightFrame);
        window.clearTimeout(spawnTimeout);
        window.clearTimeout(planetHitTimeout);
        window.removeEventListener('mousemove', onMouseMove);
        window.removeEventListener('touchmove', onTouchMove);
        window.removeEventListener('resize', refreshGeometry);
        if (retryEl) {
            retryEl.removeEventListener('click', handleRetry);
        }
        planetWrap.classList.remove('is-hit');
        planetWrap.classList.remove('is-destroyed');
        root.classList.remove('is-game-over');
        root.classList.remove('is-won');
        isWin = false;
        winStarsBuilt = false;
        if (winStarsEl) {
            winStarsEl.remove();
            winStarsEl = null;
        }
        removeStar();
    };
};
