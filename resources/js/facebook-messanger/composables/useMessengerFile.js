/**
 * Composable for Facebook Messenger template file handling.
 * Mirrors the getAcceptedFileTypes / getFileTypeDescription pattern
 * used in TemplateEditor.vue (dynamic-template).
 */
export function useMessengerFile() {

    const getAcceptedFileTypes = (contentType) => {
        switch (contentType) {
            case 'image':    return 'image/jpeg,image/png,image/gif,image/webp';
            case 'video':    return 'video/mp4,video/quicktime,video/x-msvideo';
            case 'document': return '.pdf,.doc,.docx,.txt';
            default:         return '*/*';
        }
    };

    const getFileTypeDescription = (contentType) => {
        switch (contentType) {
            case 'image':    return 'Supported formats: JPG, PNG, GIF, WebP (Max 5 MB)';
            case 'video':    return 'Supported formats: MP4, MOV, AVI (Max 25 MB)';
            case 'document': return 'Supported formats: PDF, DOC, DOCX, TXT (Max 25 MB)';
            default:         return '';
        }
    };

    /**
     * Returns an error string if validation fails, or null if the file is valid.
     */
    const validateFile = (file, contentType) => {
        const maxSizes = { image: 5, video: 25, document: 25 };
        const allowedMimes = {
            image:    ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            video:    ['video/mp4', 'video/quicktime', 'video/x-msvideo'],
            document: [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'text/plain',
            ],
        };

        const maxMB = maxSizes[contentType] ?? 25;
        const mimes = allowedMimes[contentType] ?? [];

        if (file.size > maxMB * 1024 * 1024) {
            return `File too large. Max ${maxMB} MB allowed.`;
        }

        if (mimes.length && !mimes.includes(file.type)) {
            return `Invalid file type. ${getFileTypeDescription(contentType)}`;
        }

        return null;
    };

    const formatSize = (bytes) => {
        if (bytes < 1024)          return bytes + ' B';
        if (bytes < 1024 * 1024)   return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    };

    return { getAcceptedFileTypes, getFileTypeDescription, validateFile, formatSize };
}
