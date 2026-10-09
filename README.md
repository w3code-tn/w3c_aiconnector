# W3CODE AI Connector

Connector to different AI models

## Amazon Bedrock Knowledge Base

`BedrockKnowledgeBaseService` answers natural-language questions with the content of an
[Amazon Bedrock Knowledge Base](https://docs.aws.amazon.com/bedrock/latest/userguide/kb-how-retrieval.html),
along with the sources the answer is based on. With EXT:solr, the knowledge base is fed by the Solr indexing.

### Prerequisites

- A Bedrock knowledge base (vector store) with a
  [custom data source](https://docs.aws.amazon.com/bedrock/latest/userguide/kb-direct-ingestion.html):
  documents are ingested directly, no data source sync is needed.
- Access to the generation model in the knowledge base region.
- AWS credentials resolved by the
  [AWS SDK default provider chain](https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/guide_credentials.html):
  preferably the IAM role of the instance or task, otherwise the `AWS_ACCESS_KEY_ID`/`AWS_SECRET_ACCESS_KEY`
  environment variables or a shared credentials file. No AWS keys are stored in the extension configuration.

### Configuration

| Setting                                | Description                                                                              |
|----------------------------------------|------------------------------------------------------------------------------------------|
| `bedrockKnowledgeBase.region`          | AWS region of the knowledge base, e.g. `us-east-1`                                       |
| `bedrockKnowledgeBase.knowledgeBaseId` | ID of the knowledge base                                                                 |
| `bedrockKnowledgeBase.dataSourceId`    | ID of the custom data source fed by the Solr indexing, empty to disable the synchronization |
| `bedrockKnowledgeBase.modelArn`        | ARN of the model or inference profile generating the answers                            |
| `bedrockKnowledgeBase.numberOfResults` | Maximum number of source chunks retrieved to generate an answer (1-100)                  |
| `bedrockKnowledgeBase.minimumScore`    | Minimum relevance score (0-1) of the passages returned by `retrieve()`, default 0.45     |

### IAM permissions

Least-privilege policy for the TYPO3 IAM role (the `Synchronization` statement is only needed with EXT:solr):

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Sid": "Query",
            "Effect": "Allow",
            "Action": "bedrock:RetrieveAndGenerate",
            "Resource": "*"
        },
        {
            "Sid": "Retrieval",
            "Effect": "Allow",
            "Action": "bedrock:Retrieve",
            "Resource": "arn:aws:bedrock:<region>:<account>:knowledge-base/<knowledgeBaseId>"
        },
        {
            "Sid": "Generation",
            "Effect": "Allow",
            "Action": ["bedrock:InvokeModel", "bedrock:GetInferenceProfile"],
            "Resource": ["<modelArn, and the foundation models of an inference profile>"]
        },
        {
            "Sid": "Synchronization",
            "Effect": "Allow",
            "Action": ["bedrock:StartIngestionJob", "bedrock:IngestKnowledgeBaseDocuments", "bedrock:DeleteKnowledgeBaseDocuments"],
            "Resource": "arn:aws:bedrock:<region>:<account>:knowledge-base/<knowledgeBaseId>"
        }
    ]
}
```

### Usage

```php
$result = $bedrockKnowledgeBaseService->retrieveAndGenerate(
    'How can I contact you?',
    // optional overrides of the AWS knowledgeBaseConfiguration, e.g. only the current language
    ['retrievalConfiguration' => ['vectorSearchConfiguration' => ['filter' => ['equals' => ['key' => 'language', 'value' => 0]]]]],
    // optional sessionId of a previous result, to continue the conversation
    $sessionId
);

$result['answer'];                                   // generated answer
$result['sessionId'];                                // conversation session
$result['citations'][0]['text'];                     // part of the answer
$result['citations'][0]['sources'][0]['content'];    // source chunk
$result['citations'][0]['sources'][0]['metadata'];   // source metadata, e.g. title and url
```

AWS errors are thrown as `Aws\Exception\AwsException`.

From the command line, e.g. to test the knowledge base content:

```bash
vendor/bin/typo3 aiconnector:knowledge-base:ask "How should employers manage worker fatigue?"
```

Without `modelArn` (or with `--search-only`) the command shows the most relevant passages and their source, instead
of a generated answer.

### Solr synchronization

When `dataSourceId` is set and EXT:solr is installed:

- each document indexed by EXT:solr is ingested into the custom data source, with the id `<site>/<table>/<uid>/<language>`
  and the metadata `type`, `uid`, `site`, `language`, `title` and `url` (usable in retrieval filters);
- with EXT:solrfal, each indexed file is ingested once with the text extracted for Solr, in the site language of its
  metadata `language` (ISO code, e.g. `fr`) or else the default language, with the id
  `<site>/sys_file/<file uid>/<language>` and an absolute `url`;
- records removed from the Solr index (deleted, hidden...) and files removed from the solrfal queue are removed from the
  knowledge base;
- documents restricted to frontend user groups are not ingested, the knowledge base has no access control;
- errors are logged and never interrupt the Solr indexing.

To fill the knowledge base with the existing content, re-index the site in the Solr *Index Queue* module.
