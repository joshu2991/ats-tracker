interface ProgressBarProps {
    value: number;
    max: number;
    label?: string;
    color?: 'green' | 'yellow' | 'red' | 'blue';
}

export default function ProgressBar({ value, max, label, color = 'blue' }: ProgressBarProps) {
    const safeValue = Math.max(0, value);
    const safeMax = Math.max(1, max); // Prevent division by zero
    const percentage = Math.min((safeValue / safeMax) * 100, 100);

    const colorClasses = {
        green: 'bg-green-500',
        yellow: 'bg-yellow-500',
        red: 'bg-red-500',
        blue: 'bg-blue-500',
    };

    return (
        <div className="w-full">
            {label && (
                <div className="mb-1 flex justify-between text-sm">
                    <span className="font-medium text-gray-700 dark:text-gray-300">{label}</span>
                    <span className="text-gray-600 dark:text-gray-400">
                        {value} / {max}
                    </span>
                </div>
            )}
            <div className="h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                <div
                    className={`h-full transition-all duration-500 ${colorClasses[color]}`}
                    style={{ width: `${percentage}%` }}
                />
            </div>
        </div>
    );
}

