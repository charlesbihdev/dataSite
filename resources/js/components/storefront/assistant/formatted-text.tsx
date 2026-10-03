import { Fragment, type ReactNode } from "react";

/**
 * A tiny, dependency-free Markdown renderer for assistant replies. The model often emits **bold**,
 * `code`, bullet/numbered lists, and fenced ``` blocks; this turns the common subset into React nodes.
 *
 * It builds elements directly (never dangerouslySetInnerHTML), so model or echoed-user text can't inject
 * HTML — React escapes every string. Unsupported syntax simply renders as its literal text.
 */
export function FormattedText({ content }: { content: string }) {
    return <div className="space-y-2">{renderBlocks(content)}</div>;
}

type Block =
    | { kind: "p"; lines: string[] }
    | { kind: "ul"; items: string[] }
    | { kind: "ol"; items: string[] }
    | { kind: "code"; text: string };

function renderBlocks(content: string): ReactNode[] {
    const blocks = parseBlocks(content.replace(/\r\n/g, "\n"));

    return blocks.map((block, i) => {
        switch (block.kind) {
            case "code":
                return (
                    <pre
                        key={i}
                        className="overflow-x-auto rounded-lg border border-border bg-background p-2.5 font-mono text-xs"
                    >
                        <code>{block.text}</code>
                    </pre>
                );
            case "ul":
                return (
                    <ul key={i} className="list-disc space-y-1 pl-5">
                        {block.items.map((item, j) => (
                            <li key={j}>{renderInline(item)}</li>
                        ))}
                    </ul>
                );
            case "ol":
                return (
                    <ol key={i} className="list-decimal space-y-1 pl-5">
                        {block.items.map((item, j) => (
                            <li key={j}>{renderInline(item)}</li>
                        ))}
                    </ol>
                );
            default:
                return (
                    <p key={i}>
                        {block.lines.map((line, j) => (
                            <Fragment key={j}>
                                {j > 0 && <br />}
                                {renderInline(line)}
                            </Fragment>
                        ))}
                    </p>
                );
        }
    });
}

function parseBlocks(content: string): Block[] {
    const lines = content.split("\n");
    const blocks: Block[] = [];
    let i = 0;

    while (i < lines.length) {
        const line = lines[i];

        // Fenced code block: ``` … ```
        if (line.trim().startsWith("```")) {
            const body: string[] = [];
            i++;
            while (i < lines.length && !lines[i].trim().startsWith("```")) {
                body.push(lines[i]);
                i++;
            }
            i++; // skip the closing fence
            blocks.push({ kind: "code", text: body.join("\n") });
            continue;
        }

        // Unordered list: a run of "- " / "* " / "• " lines
        if (/^\s*[-*•]\s+/.test(line)) {
            const items: string[] = [];
            while (i < lines.length && /^\s*[-*•]\s+/.test(lines[i])) {
                items.push(lines[i].replace(/^\s*[-*•]\s+/, ""));
                i++;
            }
            blocks.push({ kind: "ul", items });
            continue;
        }

        // Ordered list: a run of "1. " style lines
        if (/^\s*\d+\.\s+/.test(line)) {
            const items: string[] = [];
            while (i < lines.length && /^\s*\d+\.\s+/.test(lines[i])) {
                items.push(lines[i].replace(/^\s*\d+\.\s+/, ""));
                i++;
            }
            blocks.push({ kind: "ol", items });
            continue;
        }

        // Blank line: paragraph separator
        if (line.trim() === "") {
            i++;
            continue;
        }

        // Otherwise: gather a paragraph until a blank line or a block starts
        const para: string[] = [];
        while (
            i < lines.length &&
            lines[i].trim() !== "" &&
            !lines[i].trim().startsWith("```") &&
            !/^\s*[-*•]\s+/.test(lines[i]) &&
            !/^\s*\d+\.\s+/.test(lines[i])
        ) {
            para.push(lines[i]);
            i++;
        }
        blocks.push({ kind: "p", lines: para });
    }

    return blocks;
}

// Inline: **bold**, `code`, *italic* / _italic_. Order matters so code wins over emphasis inside it.
const INLINE = /(\*\*[^*]+\*\*|`[^`]+`|\*[^*\s][^*]*\*|_[^_\s][^_]*_)/g;

function renderInline(text: string): ReactNode[] {
    const nodes: ReactNode[] = [];
    let last = 0;
    let match: RegExpExecArray | null;
    let key = 0;

    INLINE.lastIndex = 0;
    while ((match = INLINE.exec(text)) !== null) {
        if (match.index > last) nodes.push(text.slice(last, match.index));

        const token = match[0];
        if (token.startsWith("**")) {
            nodes.push(
                <strong key={key++} className="font-semibold">
                    {token.slice(2, -2)}
                </strong>,
            );
        } else if (token.startsWith("`")) {
            nodes.push(
                <code
                    key={key++}
                    className="rounded border border-border bg-background px-1 py-0.5 font-mono text-[0.85em]"
                >
                    {token.slice(1, -1)}
                </code>,
            );
        } else {
            nodes.push(
                <em key={key++} className="italic">
                    {token.slice(1, -1)}
                </em>,
            );
        }

        last = INLINE.lastIndex;
    }

    if (last < text.length) nodes.push(text.slice(last));

    return nodes;
}
