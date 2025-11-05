import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent, useRef, useState } from 'react';
import ScoreBreakdown from '../components/ScoreBreakdown';
import ScoreDisplay from '../components/ScoreDisplay';

interface Analysis {
    filename?: string;
    overall_score?: number;
    confidence?: 'high' | 'medium' | 'low';
    parseability_score?: number;
    format_score?: number;
    keyword_score?: number;
    contact_score?: number;
    content_score?: number;
    critical_issues?: string[];
    warnings?: string[];
    suggestions?: string[];
    ai_unavailable?: boolean;
    ai_error_message?: string | null;
    estimated_cost?: number;
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

                    {!analysis || analysis.overall_score === undefined ? (
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
                            {/* AI Unavailable Warning */}
                            {analysis.ai_unavailable && (
                                <div className="rounded-lg border-2 border-yellow-400 bg-yellow-50 p-4 dark:bg-yellow-900/20 dark:border-yellow-600">
                                    <div className="flex items-start">
                                        <svg
                                            className="mr-3 h-5 w-5 text-yellow-600 dark:text-yellow-400"
                                            fill="currentColor"
                                            viewBox="0 0 20 20"
                                        >
                                            <path
                                                fillRule="evenodd"
                                                d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                                clipRule="evenodd"
                                            />
                                        </svg>
                                        <div className="flex-1">
                                            <h3 className="text-sm font-medium text-yellow-800 dark:text-yellow-200">
                                                Limited Analysis Mode
                                            </h3>
                                            <p className="mt-1 text-sm text-yellow-700 dark:text-yellow-300">
                                                {analysis.ai_error_message || 'AI analysis was not available. This analysis is based on technical checks only. Some insights may be limited, but the score and critical issues are still accurate.'}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            )}

                            {/* Score Display with Confidence Badge */}
                            <div className="rounded-lg bg-white p-8 shadow dark:bg-gray-800">
                                <div className="mb-4 text-center">
                                    <h2 className="mb-2 text-2xl font-bold text-gray-900 dark:text-white">
                                        ATS Score
                                    </h2>
                                    <p className="text-sm text-gray-500 dark:text-gray-400">
                                        File: {analysis.filename}
                                    </p>
                                    {/* Confidence Badge */}
                                    {analysis.confidence && (
                                        <div className="mt-3 inline-flex">
                                            <span
                                                className={`rounded-full px-3 py-1 text-xs font-semibold ${
                                                    analysis.confidence === 'high'
                                                        ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                                                        : analysis.confidence === 'medium'
                                                        ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400'
                                                        : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'
                                                }`}
                                            >
                                                {analysis.confidence.charAt(0).toUpperCase() + analysis.confidence.slice(1)} Confidence
                                            </span>
                                        </div>
                                    )}
                                </div>
                                <ScoreDisplay score={analysis.overall_score || 0} />
                                {/* Calibration Note */}
                                <div className="mt-4 rounded-md bg-blue-50 p-3 text-center text-xs text-blue-800 dark:bg-blue-900/20 dark:text-blue-300">
                                    <p>
                                        Scores calibrated against ResumeWorded/JobScan. 65+ is good ATS compatibility.
                                    </p>
                                </div>
                            </div>

                            {/* Score Breakdown */}
                            <div className="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                                <h2 className="mb-4 text-2xl font-bold text-gray-900 dark:text-white">
                                    Score Breakdown
                                </h2>
                                <ScoreBreakdown
                                    parseabilityScore={analysis.parseability_score}
                                    formatScore={analysis.format_score}
                                    keywordScore={analysis.keyword_score}
                                    contactScore={analysis.contact_score}
                                    contentScore={analysis.content_score}
                                />
                            </div>

                            {/* Critical Issues - Only show if category score < 30 or overall score < 30 */}
                            {analysis.critical_issues && 
                             Array.isArray(analysis.critical_issues) && 
                             analysis.critical_issues.length > 0 && 
                             (analysis.overall_score < 30 || 
                              analysis.format_score < 30 || 
                              analysis.contact_score < 30) && (
                                <div className="rounded-lg border-2 border-red-400 bg-red-50 p-6 shadow dark:bg-red-900/20 dark:border-red-600">
                                    <h2 className="mb-4 flex items-center text-xl font-bold text-red-900 dark:text-red-200">
                                        <svg
                                            className="mr-2 h-5 w-5"
                                            fill="currentColor"
                                            viewBox="0 0 20 20"
                                        >
                                            <path
                                                fillRule="evenodd"
                                                d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                                clipRule="evenodd"
                                            />
                                        </svg>
                                        Critical Fixes Required
                                    </h2>
                                    <ul className="space-y-2">
                                        {analysis.critical_issues.map((issue, index) => (
                                            <li key={index} className="flex items-start text-sm text-red-800 dark:text-red-200">
                                                <span className="mr-2 mt-1">•</span>
                                                <span>{issue}</span>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}

                            {/* Warnings */}
                            {analysis.warnings && Array.isArray(analysis.warnings) && analysis.warnings.length > 0 && (
                                <div className="rounded-lg border-2 border-yellow-400 bg-yellow-50 p-6 shadow dark:bg-yellow-900/20 dark:border-yellow-600">
                                    <h2 className="mb-4 flex items-center text-xl font-bold text-yellow-900 dark:text-yellow-200">
                                        <svg
                                            className="mr-2 h-5 w-5"
                                            fill="currentColor"
                                            viewBox="0 0 20 20"
                                        >
                                            <path
                                                fillRule="evenodd"
                                                d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                                clipRule="evenodd"
                                            />
                                        </svg>
                                        Warnings
                                    </h2>
                                    <ul className="space-y-2">
                                        {analysis.warnings.map((warning, index) => (
                                            <li key={index} className="flex items-start text-sm text-yellow-800 dark:text-yellow-200">
                                                <span className="mr-2 mt-1">⚠️</span>
                                                <span>{warning}</span>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}

                            {/* Suggestions/Improvements */}
                            {analysis.suggestions && Array.isArray(analysis.suggestions) && analysis.suggestions.length > 0 && (
                                <div className="rounded-lg border-2 border-blue-400 bg-blue-50 p-6 shadow dark:bg-blue-900/20 dark:border-blue-600">
                                    <h2 className="mb-4 flex items-center text-xl font-bold text-blue-900 dark:text-blue-200">
                                        <svg
                                            className="mr-2 h-5 w-5"
                                            fill="currentColor"
                                            viewBox="0 0 20 20"
                                        >
                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                        </svg>
                                        Recommended Improvements
                                    </h2>
                                    <ul className="space-y-2">
                                        {analysis.suggestions.map((suggestion, index) => (
                                            <li key={index} className="flex items-start text-sm text-blue-800 dark:text-blue-200">
                                                <span className="mr-2 mt-1">💡</span>
                                                <span>{suggestion}</span>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}

                            {/* Footer Disclaimer */}
                            <div className="rounded-lg bg-gray-100 p-4 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                <p>
                                    This analysis is based on documented ATS best practices from industry research including TopResume, Jobscan, and Harvard Career Services. While no tool can guarantee compatibility with all ATS systems, following these recommendations significantly improves your chances of passing automated screening. Different companies use different ATS systems with varying requirements.
                                </p>
                            </div>

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
