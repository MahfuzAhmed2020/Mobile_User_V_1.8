param location string = 'centralus'
param environmentName string = 'prod'
param sqlAdministratorLogin string = 'adminuser'
param sqlAdministratorLoginPassword string
param appServicePlanSkuName string = 'B1'
param mySqlSkuName string = 'B_Gen5_1'

var resourceToken = uniqueString(subscription().id, resourceGroup().id, location, environmentName)

resource appServicePlan 'Microsoft.Web/serverfarms@2023-12-01' = {
  name: 'azasp${resourceToken}'
  location: location
  sku: {
    name: appServicePlanSkuName
  }
  properties: {
    reserved: true
  }
}

resource mySqlServer 'Microsoft.DBforMySQL/servers@2023-12-01' = {
  name: 'azmysql${resourceToken}'
  location: location
  sku: {
    name: mySqlSkuName
  }
  properties: {
    administratorLogin: sqlAdministratorLogin
    administratorLoginPassword: sqlAdministratorLoginPassword
    version: '8.0'
    sslEnforcement: 'Enabled'
    minimalTlsVersion: 'TLS1_2'
    storageProfile: {
      storageMB: 5120
      backupRetentionDays: 7
      geoRedundantBackup: 'Disabled'
    }
  }
}

resource mySqlDatabase 'Microsoft.DBforMySQL/servers/databases@2023-12-01' = {
  parent: mySqlServer
  name: 'mobile_user_db'
  properties: {}
}

resource keyVault 'Microsoft.KeyVault/vaults@2023-12-01' = {
  name: 'azkv${resourceToken}'
  location: location
  properties: {
    sku: {
      family: 'A'
      name: 'standard'
    }
    tenantId: subscription().tenantId
    accessPolicies: []
    enabledForDeployment: false
    enabledForTemplateDeployment: false
    enabledForDiskEncryption: false
    enableRbacAuthorization: true
    publicNetworkAccess: 'Enabled'
  }
}

resource managedIdentity 'Microsoft.ManagedIdentity/userAssignedIdentities@2023-12-01' = {
  name: 'azmi${resourceToken}'
  location: location
}

resource appInsights 'Microsoft.Insights/components@2020-02-02' = {
  name: 'azai${resourceToken}'
  location: location
  kind: 'web'
  properties: {
    Application_Type: 'web'
    WorkspaceResourceId: logAnalyticsWorkspace.id
  }
}

resource logAnalyticsWorkspace 'Microsoft.OperationalInsights/workspaces@2020-03-01-preview' = {
  name: 'azlaw${resourceToken}'
  location: location
  properties: {
    sku: {
      name: 'PerGB2018'
    }
    retentionInDays: 30
  }
}

resource appService 'Microsoft.Web/sites@2023-12-01' = {
  name: 'azweb${resourceToken}'
  location: location
  identity: {
    type: 'UserAssigned'
    userAssignedIdentities: {
      '${managedIdentity.id}': {}
    }
  }
  properties: {
    serverFarmId: appServicePlan.id
    httpsOnly: true
    siteConfig: {
      linuxFxVersion: 'PHP|8.2'
      cors: {
        allowedOrigins: ['*']
      }
      appSettings: [
        {
          name: 'APPLICATIONINSIGHTS_CONNECTION_STRING'
          value: appInsights.properties.ConnectionString
        }
        {
          name: 'DB_HOST'
          value: mySqlServer.properties.fullyQualifiedDomainName
        }
        {
          name: 'DB_NAME'
          value: 'mobile_user_db'
        }
        {
          name: 'DB_USER'
          value: sqlAdministratorLogin
        }
        {
          name: 'DB_PASS'
          value: sqlAdministratorLoginPassword
        }
      ]
    }
  }
}

resource diagnosticSettings 'Microsoft.Insights/diagnosticSettings@2021-05-01-preview' = {
  scope: appService
  name: 'diagnosticSettings'
  properties: {
    workspaceId: logAnalyticsWorkspace.id
    logs: [
      {
        category: 'AppServiceHTTPLogs'
        enabled: true
      }
      {
        category: 'AppServiceConsoleLogs'
        enabled: true
      }
    ]
    metrics: [
      {
        category: 'AllMetrics'
        enabled: true
      }
    ]
  }
}

resource keyVaultSecret 'Microsoft.KeyVault/vaults/secrets@2023-02-01' = {
  parent: keyVault
  name: 'dbpassword'
  properties: {
    value: sqlAdministratorLoginPassword
  }
}

resource roleAssignment 'Microsoft.Authorization/roleAssignments@2022-04-01' = {
  name: guid(resourceGroup().id, managedIdentity.id, 'b86a8fe4-44ce-4948-aee5-eccb2c155cd7')
  scope: keyVault
  properties: {
    roleDefinitionId: '/providers/Microsoft.Authorization/roleDefinitions/b86a8fe4-44ce-4948-aee5-eccb2c155cd7'
    principalId: managedIdentity.properties.principalId
    principalType: 'ServicePrincipal'
  }
}