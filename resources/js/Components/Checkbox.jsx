export default function Checkbox({ className = '', onCheckedChange, onChange, ...props }) {
    const handleChange = (event) => {
        onChange?.(event);
        onCheckedChange?.(event.target.checked);
    };

    return (
        <input
            {...props}
            type="checkbox"
            onChange={handleChange}
            className={
                'rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 ' +
                className
            }
        />
    );
}
