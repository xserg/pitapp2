# PIT ToC Backend
This repository is managed with `yarn workspaces` and developers should use yarn in the root
of the repository to orchestrate overall application build, running, and testing.  When running
through yarn, the docker container the application runs in is also used for installing dependencies
and running tests.  By using the docker container to do this, is eases the setup for developers by as
they will not have to worry about installing and enabling all the required modules locally. Which also helps
to ensure that if it runs locally it will run in production.

## Local Testing

There are certain features that are tested differently locally than in dev/stage/prod. Consider this a living
document and as special testing circumstances are discovered, they are documented here.

### Email

### Data Import
