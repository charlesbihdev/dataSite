import type { SVGAttributes } from "react";

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 44 40"
            xmlns="http://www.w3.org/2000/svg"
            stroke="currentColor"
            strokeWidth="4"
            fill="none"
            strokeLinecap="round"
            strokeLinejoin="round"
        >
            {/* D */}
            <path d="M 6 8 V 32 M 6 8 H 14 C 22 8 22 32 14 32 H 6" />
            {/* H */}
            <path d="M 26 8 V 32 M 38 8 V 32 M 26 20 H 38" />
        </svg>
    );
}
