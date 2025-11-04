import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent, useRef, useState } from 'react';
import KeywordsList from '../components/KeywordsList';
import ScoreBreakdown from '../components/ScoreBreakdown';
import ScoreDisplay from '../components/ScoreDisplay';
import SuggestionsPanel from '../components/SuggestionsPanel';

interface Analysis {
    filename?: string;
    parsedText?: string;
    totalScore?: number;
    formatScore?: { score: number; breakdown: Record<string, number> };
    contactScore?: { score: number; breakdown: Record<string, number> };
    keywordAnalysis?: { keywords: Record<string, number>; uniqueCount: number; score: number };
    lengthScore?: { score: number; wordCount: number; breakdown: Record<string, number> };
    suggestions?: string[];
}

interface Props {
    analysis?: Analysis;
}

export default function ResumeChecker({ analysis }: Props) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [dragActive, setDragActive] = useState(false);
    const [selectedFile, setSelectedFile] = useState<File | null>(null);

    const { data, setData, post, processing, errors, reset } = useForm({
        resume: null as File | null,
    });

    const handleDrag = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        if (e.type === 'dragenter' || e.type === 'dragover') {
            setDragActive(true);
        } else if (e.type === 'dragleave') {
            setDragActive(false);
        }
    };

    const handleDrop = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setDragActive(false);

        if (e.dataTransfer.files && e.dataTransfer.files[0]) {
            handleFileSelect(e.dataTransfer.files[0]);
        }
    };

    const handleFileSelect = (file: File) => {
        // Validate file type
        const validTypes = ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        const validExtensions = ['.pdf', '.docx'];

        const fileExtension = '.' + file.name.split('.').pop()?.toLowerCase();
        const isValidType = validTypes.includes(file.type) || validExtensions.includes(fileExtension);

        if (!isValidType) {
            alert('Please upload a PDF or DOCX file.');
            return;
        }

        // Validate file size (5MB max)
        if (file.size > 5 * 1024 * 1024) {
            alert('File size must be less than 5MB.');
            return;
        }

        setSelectedFile(file);
        setData('resume', file);
        reset('errors');
    };

    const handleFileInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (e.target.files && e.target.files[0]) {
            handleFileSelect(e.target.files[0]);
        }
    };

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        if (data.resume) {
            post('/resume/analyze', {
                forceFormData: true,
            });
        }
    };

    const handleReset = () => {
        setSelectedFile(null);
        reset();
        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
        // Navigate to the page without analysis to reset the view
        router.visit('/resume-checker');
    };

    return (
        <>
            <Head title="Resume ATS Checker" />

            <div className="min-h-screen bg-gray-50 py-12 px-4 dark:bg-gray-900 sm:px-6 lg:px-8">
                <div className="mx-auto max-w-4xl">
                    <div className="mb-8 text-center">
                        <h1 className="text-4xl font-bold text-gray-900 dark:text-white">
                            Resume ATS Checker
                        </h1>
                        <p className="mt-2 text-lg text-gray-600 dark:text-gray-400">
                            Upload your resume to analyze its ATS compatibility
                        </p>
                    </div>

                    {!analysis || !analysis.totalScore ? (
                        <form onSubmit={handleSubmit}>
                            <div
                                className={`relative rounded-lg border-2 border-dashed p-8 transition-colors ${
                                    dragActive
                                        ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20'
                                        : 'border-gray-300 bg-white dark:border-gray-600 dark:bg-gray-800'
                                } ${errors.resume ? 'border-red-500' : ''}`}
                                onDragEnter={handleDrag}
                                onDragLeave={handleDrag}
                                onDragOver={handleDrag}
                                onDrop={handleDrop}
                            >
                                <input
                                    ref={fileInputRef}
                                    type="file"
                                    accept=".pdf,.docx"
                                    onChange={handleFileInputChange}
                                    className="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                                    disabled={processing}
                                />

                                <div className="text-center">
                                    <svg
                                        className="mx-auto h-12 w-12 text-gray-400"
                                        stroke="currentColor"
                                        fill="none"
                                        viewBox="0 0 48 48"
                                    >
                                        <path
                                            d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02"
                                            strokeWidth={2}
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                        />
                                    </svg>
                                    <div className="mt-4">
                                        <p className="text-lg font-medium text-gray-900 dark:text-white">
                                            {selectedFile ? (
                                                <span className="text-blue-600 dark:text-blue-400">
                                                    {selectedFile.name}
                                                </span>
                                            ) : (
                                                <>
                                                    <span className="text-blue-600 dark:text-blue-400">Click to upload</span> or drag and drop
                                                </>
                                            )}
                                        </p>
                                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                            PDF or DOCX (Max 5MB)
                                        </p>
                                    </div>
                                </div>
                            </div>

                            {errors.resume && (
                                <div className="mt-4 rounded-md bg-red-50 p-4 dark:bg-red-900/20">
                                    <p className="text-sm text-red-800 dark:text-red-200">{errors.resume}</p>
                                </div>
                            )}

                            {selectedFile && (
                                <div className="mt-6 flex gap-4">
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="flex-1 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 dark:bg-blue-500 dark:hover:bg-blue-600"
                                    >
                                        {processing ? (
                                            <span className="flex items-center justify-center">
                                                <svg
                                                    className="mr-2 h-4 w-4 animate-spin"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <circle
                                                        className="opacity-25"
                                                        cx="12"
                                                        cy="12"
                                                        r="10"
                                                        stroke="currentColor"
                                                        strokeWidth="4"
                                                    />
                                                    <path
                                                        className="opacity-75"
                                                        fill="currentColor"
                                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                                    />
                                                </svg>
                                                Analyzing...
                                            </span>
                                        ) : (
                                            'Analyze Resume'
                                        )}
                                    </button>
                                    <button
                                        type="button"
                                        onClick={handleReset}
                                        disabled={processing}
                                        className="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                                    >
                                        Reset
                                    </button>
                                </div>
                            )}
                        </form>
                    ) : (
                        <div className="space-y-6">
                            {/* Score Display */}
                            <div className="rounded-lg bg-white p-8 shadow dark:bg-gray-800">
                                <div className="mb-4 text-center">
                                    <h2 className="mb-2 text-2xl font-bold text-gray-900 dark:text-white">
                                        ATS Score
                                    </h2>
                                    <p className="text-sm text-gray-500 dark:text-gray-400">
                                        File: {analysis.filename}
                                    </p>
                                </div>
                                <ScoreDisplay score={analysis.totalScore || 0} />
                            </div>

                            {/* Score Breakdown */}
                            {analysis.formatScore && analysis.contactScore && analysis.keywordAnalysis && analysis.lengthScore && (
                                <div className="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                                    <h2 className="mb-4 text-2xl font-bold text-gray-900 dark:text-white">
                                        Score Breakdown
                                    </h2>
                                    <ScoreBreakdown
                                        formatScore={analysis.formatScore}
                                        contactScore={analysis.contactScore}
                                        keywordScore={analysis.keywordAnalysis?.score || 0}
                                        lengthScore={analysis.lengthScore}
                                    />
                                </div>
                            )}

                            {/* Suggestions */}
                            {analysis.suggestions && Array.isArray(analysis.suggestions) && analysis.suggestions.length > 0 && (
                                <SuggestionsPanel suggestions={analysis.suggestions} />
                            )}

                            {/* Keywords */}
                            {analysis.keywordAnalysis && analysis.keywordAnalysis.keywords && (
                                <KeywordsList
                                    keywords={analysis.keywordAnalysis.keywords}
                                    uniqueCount={analysis.keywordAnalysis.uniqueCount || 0}
                                />
                            )}

                            {/* Action Button */}
                            <div className="text-center">
                                <button
                                    onClick={handleReset}
                                    className="rounded-md bg-blue-600 px-6 py-3 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:bg-blue-500 dark:hover:bg-blue-600"
                                >
                                    Analyze Another Resume
                                </button>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
