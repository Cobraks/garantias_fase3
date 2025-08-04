"use strict";

const listeners = new Map();

function on(event, handler) {
        if (!listeners.has(event)) {
                listeners.set(event, new Set());
        }
        listeners.get(event).add(handler);
}

function off(event, handler) {
        const handlers = listeners.get(event);
        if (!handlers) return;
        handlers.delete(handler);
        if (handlers.size === 0) listeners.delete(event);
}

function emit(event, detail) {
        const handlers = listeners.get(event);
        if (!handlers) return;
        handlers.forEach((fn) => {
                try {
                        fn(detail);
                } catch (err) {
                        console.error(err);
                }
        });
}

export default { on, off, emit };
export { on, off, emit };
