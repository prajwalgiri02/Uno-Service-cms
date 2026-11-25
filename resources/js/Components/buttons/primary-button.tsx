import type { ButtonHTMLAttributes, ReactNode } from 'react';

type PrimaryButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & {
    variant?: 'primary' | 'outline';
    size?: 'large' | 'medium';
    block?: boolean;
    children: ReactNode;
};

export default function PrimaryButton({
    variant = 'primary',
    size = 'large',
    block = false,
    type = 'button',
    className = '',
    children,
    ...rest
}: PrimaryButtonProps) {
    const classes = ['btn', `btn-${variant}`, `btn-${size}`, block ? 'btn-block' : '', className].filter(Boolean).join(' ');

    return (
        <button type={type} className={classes} {...rest}>
            {children}
        </button>
    );
}
