import ProgressBar from './ProgressBar';

interface ScoreBreakdownProps {
    formatScore: { score: number; breakdown: Record<string, number> };
    contactScore: { score: number; breakdown: Record<string, number> };
    keywordScore: number;
    lengthScore: { score: number; wordCount: number; breakdown: Record<string, number> };
}

export default function ScoreBreakdown({
    formatScore,
    contactScore,
    keywordScore,
    lengthScore,
}: ScoreBreakdownProps) {
    const getScoreColor = (score: number, max: number): 'green' | 'yellow' | 'red' => {
        const percentage = (score / max) * 100;
        if (percentage >= 80) {
            return 'green';
        }
        if (percentage >= 60) {
            return 'yellow';
        }
        return 'red';
    };

    return (
        <div className="space-y-6">
            <div>
                <h3 className="mb-2 text-lg font-semibold text-gray-900 dark:text-white">Format Score</h3>
                <ProgressBar
                    value={formatScore.score}
                    max={30}
                    color={getScoreColor(formatScore.score, 30)}
                />
                <div className="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Experience: {formatScore.breakdown.experience}/10, Education:{' '}
                    {formatScore.breakdown.education}/10, Skills: {formatScore.breakdown.skills}/5, Bullets:{' '}
                    {formatScore.breakdown.bullets}/5
                </div>
            </div>

            <div>
                <h3 className="mb-2 text-lg font-semibold text-gray-900 dark:text-white">Keyword Score</h3>
                <ProgressBar value={keywordScore} max={40} color={getScoreColor(keywordScore, 40)} />
            </div>

            <div>
                <h3 className="mb-2 text-lg font-semibold text-gray-900 dark:text-white">Contact Score</h3>
                <ProgressBar
                    value={contactScore.score}
                    max={10}
                    color={getScoreColor(contactScore.score, 10)}
                />
                <div className="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Email: {contactScore.breakdown.email}/3, Phone: {contactScore.breakdown.phone}/2, LinkedIn:{' '}
                    {contactScore.breakdown.linkedin}/3, GitHub: {contactScore.breakdown.github}/2
                </div>
            </div>

            <div>
                <h3 className="mb-2 text-lg font-semibold text-gray-900 dark:text-white">Length & Clarity Score</h3>
                <ProgressBar
                    value={lengthScore.score}
                    max={20}
                    color={getScoreColor(lengthScore.score, 20)}
                />
                <div className="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Word Count: {lengthScore.wordCount} (ideal: 400-800), Action Verbs:{' '}
                    {lengthScore.breakdown.actionVerbs}/5, Bullets: {lengthScore.breakdown.bullets}/5
                </div>
            </div>
        </div>
    );
}

