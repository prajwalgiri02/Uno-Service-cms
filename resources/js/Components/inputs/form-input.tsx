import { Eye, EyeOff } from 'lucide-react';
import { useState, type InputHTMLAttributes } from 'react';

type FormInputProps = Omit<InputHTMLAttributes<HTMLInputElement>, 'className'> & {
    name: string;
    label: string;
    error?: string;
};

export default function FormInput({ name, label, error, type = 'text', id, ...rest }: FormInputProps) {
    const [visible, setVisible] = useState(false);
    const inputId = id ?? `input-${name}`;
    const isPassword = type === 'password';

    const input = (
        <input
            id={inputId}
            name={name}
            type={isPassword && visible ? 'text' : type}
            className={`form-control${error ? ' has-error' : ''}`}
            aria-invalid={Boolean(error)}
            aria-describedby={error ? `${inputId}-error` : undefined}
            {...rest}
        />
    );

    return (
        <div className="form-group">
            <label className="form-label" htmlFor={inputId}>
                {label}
            </label>
            {isPassword ? (
                <div className="input-wrap">
                    {input}
                    <button
                        type="button"
                        className="input-icon-right"
                        onClick={() => setVisible((v) => !v)}
                        aria-label={visible ? 'Hide password' : 'Show password'}
                    >
                        {visible ? <EyeOff size={18} /> : <Eye size={18} />}
                    </button>
                </div>
            ) : (
                input
            )}
            {error && (
                <p id={`${inputId}-error`} className="field-error">
                    {error}
                </p>
            )}
        </div>
    );
}
