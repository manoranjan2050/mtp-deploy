import { Terminal } from '@xterm/xterm';
import { FitAddon } from '@xterm/addon-fit';
import '@xterm/xterm/css/xterm.css';

/**
 * A deliberately simple line-buffered terminal, not a real PTY - there is no
 * raw-mode keystroke pass-through to a remote shell. Each Enter press submits
 * the buffered line to `onSubmit`, which is expected to return a promise
 * resolving to { output, prompt, requiresConfirmation }. See CLAUDE.md for
 * why Module 8 (Terminal) is scoped this way instead of a full WebSocket/PTY
 * bridge.
 */
export function initMtpTerminal(el, { prompt, onSubmit }) {
    // Guards against double-initialization: Livewire's morph hook and
    // Alpine's own DOM observer can both end up processing the same
    // wire:ignore'd node for a single tab, and Alpine's own already-attached
    // check happens at the x-data level, not inside this plain function -
    // so the idempotency has to live here, keyed off the real DOM element.
    if (el._mtpTerminal) {
        return el._mtpTerminal;
    }

    const term = new Terminal({
        convertEol: true,
        fontSize: 13,
        cursorBlink: true,
        theme: { background: '#0b1120', foreground: '#e2e8f0' },
    });
    const fitAddon = new FitAddon();
    term.loadAddon(fitAddon);
    term.open(el);
    fitAddon.fit();

    let buffer = '';
    let busy = false;

    const writeOutput = (text) => {
        if (!text) {
            return;
        }
        term.write(text.replace(/\r?\n/g, '\r\n'));
        if (!text.endsWith('\n')) {
            term.write('\r\n');
        }
    };

    const writePrompt = (text) => term.write(text);

    writePrompt(prompt);

    term.onData((data) => {
        if (busy) {
            return;
        }

        if (data === '\r') {
            const line = buffer;
            buffer = '';
            term.write('\r\n');

            if (line.trim() === '') {
                writePrompt(prompt);
                return;
            }

            busy = true;
            onSubmit(line).then((result) => {
                writeOutput(result.output);
                writePrompt(result.prompt);
                busy = false;
            }).catch(() => {
                writeOutput('(terminal error - see browser console)');
                writePrompt(prompt);
                busy = false;
            });
        } else if (data === '') {
            if (buffer.length > 0) {
                buffer = buffer.slice(0, -1);
                term.write('\b \b');
            }
        } else if (data >= ' ' || data === '\t') {
            buffer += data;
            term.write(data);
        }
    });

    const publicApi = {
        fit: () => fitAddon.fit(),
        focus: () => term.focus(),
        dispose: () => term.dispose(),
    };

    el._mtpTerminal = publicApi;

    return publicApi;
}

window.initMtpTerminal = initMtpTerminal;
